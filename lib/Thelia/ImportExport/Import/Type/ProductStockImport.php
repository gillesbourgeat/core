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

namespace Thelia\ImportExport\Import\Type;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductStockImport
 * @package Thelia\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductStockImport extends ImportHandler
{
    /**
     * @param \Thelia\Core\FileFormat\Formatting\FormatterData
     * @return string|array error messages
     *
     * The method does the import routine from a FormatterData
     */
    public function retrieveFromFormatterData(FormatterData $data)
    {
        $errors = [];
        $translator = Translator::getInstance();

        while (null !== $row = $data->popRow()) {
            $obj = ProductSaleElementsQuery::create()->findOneByRef($row["ref"]);

            if ($obj === null) {
                $errorMessage = $translator->trans(
                    "The product sale element reference %ref doesn't exist",
                    [
                        "%ref" => $row["ref"]
                    ]
                );

                $errors[] = $errorMessage ;
            } else {
                $obj->setQuantity($row["stock"])->save();
            }
        }

        return $errors;
    }

    /**
     * @return string|array
     *
     * Define all the type of import/formatters that this can handle
     * return a string if it handle a single type ( specific exports ),
     * or an array if multiple.
     *
     * Thelia types are defined in \Thelia\Core\FileFormat\FormatType
     *
     * example:
     * return array(
     *     FormatType::TABLE,
     *     FormatType::UNBOUNDED,
     * );
     */
    public function getHandledTypes()
    {
        return array(
            FormatType::TABLE,
            FormatType::UNBOUNDED,
        );
    }

} 