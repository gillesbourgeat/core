<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;
use Thelia\Core\Event\ImportExport as ImportExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManagerTrait;
use Thelia\Core\FileFormat\Formatting\FormatterManagerTrait;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Import as ImportLoop;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ImportForm;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Class ImportController
 * @package Thelia\Controller\Admin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportController extends BaseAdminController
{
    use FormatterManagerTrait;
    use ArchiveBuilderManagerTrait;

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::VIEW])) {
            return $response;
        }

        $this->setOrders();

        return $this->render('import');
    }

    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/import/{id}
     * is called with a POST request.
     */
    public function import($id)
    {
        if (null === $import = $this->getImport($id)) {
            return $this->render("404");
        }

        $archiveBuilderManager = $this->getArchiveBuilderManager($this->container);
        $formatterManager = $this->getFormatterManager($this->container);

        /**
         * Get needed services
         */
        $form = new ImportForm($this->getRequest());
        $errorMessage = null;
        $successMessage = null;


        try {
            $boundForm = $this->validateForm($form);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $boundForm->get("file_upload")->getData();

            /**
             * We have to check the extension manually because of composed file formats as tar.gz or tar.bz2
             */
            $name = $file->getClientOriginalName();
            $nameLength = strlen($name);


            $handler = $import->getHandleClassInstance($this->container);
            $types = $handler->getHandledTypes();

            $formats =
                $formatterManager->getExtensionsByTypes($types, true) +
                $archiveBuilderManager->getExtensions(true)
            ;

            $uploadFormat = null;

            /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
            $formatter = null;

            /** @var \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder $archiveBuilder */
            $archiveBuilder = null;

            foreach ($formats as $objectName => $format) {
                $formatLength = strlen($format);
                $formatExtension = substr($name, -$formatLength);

                if ($nameLength >= $formatLength  && $formatExtension === $format) {
                    $uploadFormat = $format;


                    try {
                        $formatter = $formatterManager->get($objectName);
                    } catch(\OutOfBoundsException $e) {}

                    try {
                        $archiveBuilder = $archiveBuilderManager->get($objectName);
                    } catch(\OutOfBoundsException $e) {}

                    break;
                }
            }

            $splitName = explode(".", $name);
            $ext = "";

            if (1 < $limit = count($splitName)) {
                $ext = "." . $splitName[$limit-1];
            }

            if ($uploadFormat === null) {
                throw new FormValidationException(
                    $this->getTranslator()->trans(
                        "The extension \"%ext\" is not allowed",
                        [
                            "%ext" => $ext
                        ]
                    )
                );
            }

            if ($archiveBuilder !== null) {
                /**
                 * If the file is an archive
                 */
                $archiveBuilder = $archiveBuilder->loadArchive($file->getPathname());
                $content = null;

                /**
                 * Check expected file names for each formatter
                 */

                $fileNames = [];
                /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
                foreach ($formatterManager->getFormattersByTypes($types) as $formatter) {
                    $fileName = $formatter::FILENAME . "." . $formatter->getExtension();
                    $fileNames[] = $fileName;

                    if ($archiveBuilder->hasFile($fileName)) {
                        $content = $archiveBuilder->getFileContent($fileName);
                        break;
                    }
                }

                if ($content === null) {
                    throw new \ErrorException(
                        $this->getTranslator()->trans(
                            "Your archive must contain one of these file and doesn't: %files",
                            [
                                "%files" => implode(", ", $fileNames),
                            ]
                        )
                    );
                }
            } elseif ($formatter !== null) {
                /**
                 * If the file isn't an archive
                 */
                $content = file_get_contents($file->getPathname());

            } else {
                throw new \ErrorException(
                    $this->getTranslator()->trans(
                        "There's a problem, the extension \"%ext\" has been found, ".
                        "but has no formatters nor archive builder",
                        [
                            "%ext" => $ext
                        ]
                    )
                );
            }

            $event = new ImportExportEvent($formatter, $handler, null, $archiveBuilder);
            $event->setContent($content);

            $this->dispatch(TheliaEvents::IMPORT_AFTER_DECODE, $event);

            $data = $formatter->decode($event->getContent());

            $event->setContent(null)->setData($data);
            $this->dispatch(TheliaEvents::IMPORT_AFTER_DECODE, $event);

            $errors = $handler->retrieveFromFormatterData($data);

            if (!empty($errors)) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        "Errors occurred while importing the file: %errors",
                        [
                            "%errors" => implode(", ", $errors),
                        ]
                    )
                );
            }

            $successMessage = $this->getTranslator()->trans("Import successfully done");

        } catch(FormValidationException $e) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($e);
        } catch(\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if ($successMessage !== null) {
            $this->getParserContext()->set("success_message", $successMessage);
        }

        if ($errorMessage !== null) {
            $form->setErrorMessage($errorMessage);

            $this->getParserContext()
                ->addForm($form)
                ->setGeneralError($errorMessage)
            ;
        }

        return $this->importView($id);
    }


    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/import/{id}
     * is called with a GET request.
     *
     * It returns a modal view if the request is an AJAX one,
     * otherwise it generates a "normal" back-office page
     */
    public function importView($id)
    {
        if (null === $import = $this->getImport($id)) {
            return $this->render("404");
        }

        /**
         * Use the loop to inject the same vars in Smarty
         */
        $loop = new ImportLoop($this->container);

        $loop->initializeArgs([
            "export" => $import->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name=>$value) {
                $parserContext->set($name, $value);
            }
        }

        /**
         * Get allowed formats
         */
        /** @var \Thelia\ImportExport\AbstractHandler $handler */
        $handler = $import->getHandleClassInstance($this->container);
        $types = $handler->getHandledTypes();

        $formatterManager = $this->getFormatterManager($this->container);
        $archiveBuilderManager = $this->getArchiveBuilderManager($this->container);

        $formats =
            $formatterManager->getExtensionsByTypes($types, true) +
            $archiveBuilderManager->getExtensions(true)
        ;

        /**
         * Get allowed mime types (used for the "Search a file" window
         */
        $mimeTypes =
            $formatterManager->getMimeTypesByTypes($types) +
            $archiveBuilderManager->getMimeTypes()
        ;

        /**
         * Inject them in smarty
         */
        $parserContext
            ->set( "ALLOWED_MIME_TYPES", implode(",", $mimeTypes))
            ->set("ALLOWED_EXTENSIONS", implode(", ", $formats))
        ;

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/import-modal");
        } else {
            return $this->render("import-page");
        }
    }

    protected function setOrders($category = null, $import = null)
    {
        if ($category === null) {
            $category = $this->getRequest()->query->get("category_order", "manual");
        }

        if ($import === null) {
            $import = $this->getRequest()->query->get("import_order", "manual");
        }

        $this->getParserContext()
            ->set("category_order", $category)
        ;

        $this->getParserContext()
            ->set("import_order", $import)
        ;
    }

    public function changePosition($action, $id)
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $import = $this->getImport($id);

        if ($action === "up") {
            $import->upPosition();
        } elseif ($action === "down") {
            $import->downPosition();
        }

        $this->setOrders(null, "manual");

        return $this->render('import');
    }

    public function updatePosition($id, $value)
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $import = $this->getImport($id);

        $import->updatePosition($value);

        $this->setOrders(null, "manual");

        return $this->render('import');
    }

    public function changeCategoryPosition($action, $id)
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $category = $this->getCategory($id);

        if ($action === "up") {
            $category->upPosition();
        } elseif ($action === "down") {
            $category->downPosition();
        }

        $this->setOrders("manual");

        return $this->render('import');
    }

    public function updateCategoryPosition($id, $value)
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $category = $this->getCategory($id);

        $category->updatePosition($value);

        $this->setOrders("manual");

        return $this->render('import');
    }

    protected function getImport($id)
    {
        $import = ImportQuery::create()->findPk($id);

        if (null === $import) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the imports",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $import;
    }

    protected function getCategory($id)
    {
        $category = ImportCategoryQuery::create()->findPk($id);

        if (null === $category) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the import categories",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $category;
    }
} 