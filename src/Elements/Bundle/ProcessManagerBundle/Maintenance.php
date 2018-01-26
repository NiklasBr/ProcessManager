<?php

namespace Elements\Bundle\ProcessManagerBundle;

use Elements\Bundle\ProcessManagerBundle\Model\Configuration;
use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use Carbon\Carbon;
use Symfony\Component\Templating\EngineInterface;

class Maintenance {


    /**
     * @var MonitoringItem
     */
    protected $monitoringItem;

    protected $renderingEngine;


    public function __construct(EngineInterface $renderingEngine )
    {
        $this->renderingEngine = $renderingEngine;
    }

    public function execute(){
        $this->monitoringItem = ElementsProcessManagerBundle::getMonitoringItem();
        $this->monitoringItem->setTotalSteps(3)->save();
        $this->checkProcesses();
        $this->executeCronJobs();
        $this->clearMonitoringLogs();
    }

    public function checkProcesses(){
        $this->monitoringItem->setCurrentStep(1)->setStatus('Checking processes')->save();

        $this->monitoringItem->getLogger()->debug('Checking processes');

        $list = new MonitoringItem\Listing();
        $list->setCondition('IFNULL(reportedDate,0) = 0 ');
        $items = $list->load();
        $reportItems = [];

        $config = ElementsProcessManagerBundle::getConfig();

        foreach($items as $item) {
            if(!$item->getCommand()){ //manually created - do not check
                $item->setReportedDate(1)->save(true);
            }else{
                if ($item->isAlive()) {
                    $diff = time() - $item->getModificationDate();
                    $minutes = $config['general']['processTimeoutMinutes'] ?: 15;
                    if ($diff > (60 * $minutes)) {
                        $item->getLogger()->error('Process was checked by ProcessManager maintenance. Considered as hanging process - TimeDiff: ' . $diff . ' seconds.');
                        $reportItems[] = $item;
                    }
                } else {
                    if ($item->getStatus() == $item::STATUS_FINISHED) {
                        $item->getLogger()->info('Process was checked by ProcessManager maintenance and considered as successfull process.');
                        $item->setReportedDate(time())->save(true);
                    } else {
                        $item->setMessage('Process died. ' . $item->getMessage() . ' Last State: ' . $item->getStatus(),false)->setStatus($item::STATUS_FAILED);
                        $item->getLogger()->error('Process was checked by ProcessManager maintenance and considered as dead process');
                        $this->monitoringItem->getLogger()->error('Monitoring item ' . $item->getId() . ' was checked by ProcessManager maintenance and considered as dead process');
                        $reportItems[] = $item;
                    }
                }
            }

        }

        if($reportItems){
            $config = ElementsProcessManagerBundle::getConfig();
            $mail = new \Pimcore\Mail();
            $mail->setSubject('ProcessManager - failed processes (' . \Pimcore\Tool::getHostUrl().')');

            $html = $this->renderingEngine->render('ElementsProcessManagerBundle::report-email.html.php', array(
                "reportItems" => $reportItems
            ));

            $mail->setBodyHtml($html);

            $recipients = $config['email']['recipients'];

            $recipients = array_shift($recipients);
            if ($recipients) {
                $mail->addTo($recipients);
                if (!empty($recipients)) {
                    $mail->addCc($recipients);
                }
                try {
                    $mail->send();
                }catch(\Exception $e){
                    $logger = \Pimcore\Log\ApplicationLogger::getInstance("ProcessManager", true); // returns a PSR-3 compatible logger
                    $message = "Can't send E-Mail: " . $e->getMessage();
                    $logger->emergency($message);
                    \Pimcore\Logger::emergency($message);
                }
            }
        }
        /**
         * @var $item MonitoringItem
         */
        foreach($reportItems as $item){
            $item->setReportedDate(time())->save();
        }
        $this->monitoringItem->setStatus('Processes checked')->save();
    }

    public function executeCronJobs(){
        $this->monitoringItem->setCurrentStep(2)->setMessage('Checking cronjobs')->save();

        $logger = $this->monitoringItem->getLogger();
        $list = new Configuration\Listing();
        $list->setCondition('cronjob != "" AND active=1 ');
        $configs = $list->load();
        $logger->notice('Checking ' . count($configs).' Jobs');
        foreach($configs as $config){
            $currentTs = time();
            $nextRunTs = $config->getNextCronJobExecutionTimestamp();

            $message = 'Checking Job: ' . $config->getName().' (ID: '.$config->getId().') Last execution: ' . date('Y-m-d H:i:s',$config->getLastCronJobExecution());
            $message .= ' Next execution: ' . date('Y-m-d H:i:s',$nextRunTs);
            $logger->debug($message);
            $diff = $nextRunTs-$currentTs;
            if($diff < 0){
                $result = Helper::executeJob($config->getId(),[],0);
                if($result['success']){
                    $logger->debug('Execution job: ' . $config->getName().' ID: ' . $config->getId().' Diff:' . $diff.' Command: '. $result['executedCommand']);
                    $config->setLastCronJobExecution(time())->save();
                }else{
                    $logger->emergency("Can't start the Cronjob. Data: " . print_r($result,true));
                }
            }else{
                $logger->debug('Skipping job: ' . $config->getName().' ID: ' . $config->getId().' Diff:' . $diff);
            }
        }

        $this->monitoringItem->setMessage('Cronjobs executed')->setCompleted();
    }

    protected function clearMonitoringLogs(){
        $this->monitoringItem->setCurrentStep(3)->setMessage('Clearing monitoring logs')->save();
        $logger = $this->monitoringItem->getLogger();

        $treshold =  ElementsProcessManagerBundle::getConfig()['general']['archive_treshold_logs'];
        if($treshold){
            $timestamp = Carbon::createFromTimestamp(time())->subDay(1)->getTimestamp();
            $list = new MonitoringItem\Listing();
            $list->setCondition('modificationDate <= '. $timestamp);
            $items = $list->load();
            $logger->debug('Deleting ' . count($items).' monitoring items.');
            foreach($items as $item){
                $logger->debug('Deleting item. Name: "' . $item->getName().'" ID: '.$item->getId() .' monitoring items.');
                $item->delete();
            }
        }else{
            $logger->notice('No treshold defined -> nothing to do.');
        }

        $logger->debug("Start clearing ProcessManager maintenance items");
        $list = new MonitoringItem\Listing();
        $list->setCondition('name ="ProcessManager maintenance" AND status="finished"');
        $list->setOrderKey('id')->setOrder('DESC');
        $list->setOffset(5);
        foreach($list->load() as $item){
            $logger->debug("Deleting monitoring Item: " . $item->getId());
            $item->delete();
        }
        $logger->debug("Clearing ProcessManager items finished");

        $this->monitoringItem->setMessage('Clearing monitoring done')->setCompleted();
    }
}
