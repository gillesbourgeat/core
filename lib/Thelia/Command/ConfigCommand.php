<?php
/*******************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace Thelia\Command;

use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;


/**
 * Class ConfigCommand
 * @package Thelia\Command
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConfigCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName("thelia:config")
            ->setDescription("Manage configuration variables")
            ->addArgument(
                'COMMAND',
                InputArgument::REQUIRED,
                'Command : list, get, set, delete'
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'The vairable name'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'The variable value'
            )
            ->addOption(
                'secured',
                null,
                InputOption::VALUE_NONE,
                'When setting a new variable tell variable is secured.'
            )
            ->addOption(
                'visible',
                null,
                InputOption::VALUE_NONE,
                'When setting a new variable tell variable is visible.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument("COMMAND");

        switch ($command){
            case "list":
                $this->listConfig($input, $output);
                break;
            case "get":
                $this->getConfig($input, $output);
                break;

            case "set":
                $this->setConfig($input, $output);
                break;

            case "delete":
                $this->deleteConfig($input, $output);
                break;

            default:
                $output->writeln(
                    "<error>Unknown argument 'COMMAND' : list, get, set, delete</error>"
                );
        }
    }

    private function listConfig(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(array(
            "",
            "<error>Variables list</error>",
            ""
        ));

        $vars = ConfigQuery::create()
            ->orderByName()
            ->find()
        ;

        $rows = [];

        /** @var Config $var */
        foreach ($vars as $var){
            $rows[] = [
                $var->getName(),
                $var->getValue(),
                $var->getSecured() !== 0 ? "yes" : "no",
                $var->getHidden() !== 0 ? "yes" : "no"
            ];
        }

        $table = new TableHelper();
        $table
            ->setHeaders(array('Name', 'Value', 'secured', 'hidden'))
            ->setRows($rows)
        ;
        $table->render($output);

    }

    private function getConfig(InputInterface $input, OutputInterface $output)
    {

        $varName = $input->getArgument("name");

        if (empty($varName)) {
            $output->writeln(
                "<error>Need argument 'name' for get command</error>"
            );
            return;
        }

        $var = ConfigQuery::create()->findOneByName($varName);

        $out = [];

        if (null === $var){
            $out[] = sprintf(
                "<error>No variable with name '%s' </error>",
                $varName
            );
        } else {
            $out = [
                sprintf('%12s: <%3$s>%s</%3$s>', "Name", $var->getName(), "info"),
                sprintf('%12s: <%3$s>%s</%3$s>', "Value", $var->getValue(), "info"),
                sprintf('%12s: <%3$s>%s</%3$s>', "Secured", $var->getSecured() ? "yes" : "no", "info"),
                sprintf('%12s: <%3$s>%s</%3$s>', "Hidden", $var->getHidden() ? "yes" : "no", "info"),
                sprintf('%12s: <%3$s>%s</%3$s>', "Title", $var->getTitle(), "info"),
                sprintf('%12s: <%3$s>%s</%3$s>', "Description", $var->getDescription(), "info"),
            ];
        }

        $output->writeln($out);
    }


    private function setConfig(InputInterface $input, OutputInterface $output)
    {

        $varName = $input->getArgument("name");
        $varValue = $input->getArgument("value");

        if (empty($varName) || empty($varValue)) {
            $output->writeln(
                "<error>Need argument 'name' and 'value' for set command</error>"
            );
            return;
        }

        $varSecured = $input->getOption("secured") ? 1 : 0;
        $varHidden = $input->getOption("visible") ? 0 : 1;

        $var = ConfigQuery::create()->findOneByName($varName);

        if (null === $var){
            $var = new Config();
            $var->setName($varName);
        }

        $var
            ->setValue($varValue)
            ->setSecured($varSecured)
            ->setHidden($varHidden)
            ->save()
        ;

        $output->writeln("<info>Variable has been set</info>");
    }

    private function deleteConfig(InputInterface $input, OutputInterface $output)
    {

        $varName = $input->getArgument("name");

        if (empty($varName)) {
            $output->writeln(
                "<error>Need argument 'name' for get command</error>"
            );
            return;
        }

        $var = ConfigQuery::create()->findOneByName($varName);

        $var->delete();

        $output->writeln("<info>Variable has been deleted</info>");
    }
}