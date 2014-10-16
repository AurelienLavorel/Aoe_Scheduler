<?php

/**
 * Crontab observer.
 *
 * @author Fabrizio Branca
 */
class Aoe_Scheduler_Model_Observer /* extends Mage_Cron_Model_Observer */
{
    /**
     * Process cron queue
     * Generate tasks schedule
     * Cleanup tasks schedule
     *
     * @param Varien_Event_Observer $observer
     */
    public function dispatch(Varien_Event_Observer $observer)
    {
        if (!Mage::getStoreConfigFlag('system/cron/enable')) {
            return;
        }

        $scheduleManager = Mage::getModel('aoe_scheduler/scheduleManager'); /* @var $scheduleManager Aoe_Scheduler_Model_ScheduleManager */
        $scheduleManager->logRun();

        /* @var Aoe_Scheduler_Helper_Data $helper */
        $helper = Mage::helper('aoe_scheduler');

        $includeJobs = array_filter(array_map('trim', (array)$observer->getIncludeJobs()));
        $excludeJobs = array_filter(array_map('trim', (array)$observer->getExcludeJobs()));

        $includeGroups = array_filter(array_map('trim', (array)$observer->getIncludeGroups()));
        $includeJobs = $helper->addGroupJobs($includeJobs, $includeGroups);

        $excludeGroups = array_filter(array_map('trim', (array)$observer->getExcludeGroups()));
        $excludeJobs = $helper->addGroupJobs($excludeJobs, $excludeGroups);

        // DEPRECATED - Include ENV whitelist and blacklist
        $includeJobs = array_merge($includeJobs, $scheduleManager->getWhitelist());
        $excludeJobs = array_merge($excludeJobs, $scheduleManager->getBlacklist());

        $schedules = $scheduleManager->getPendingSchedules($includeJobs, $excludeJobs); /* @var $schedules Mage_Cron_Model_Resource_Schedule_Collection */
        foreach ($schedules as $schedule) { /* @var $schedule Aoe_Scheduler_Model_Schedule */
            $schedule->process();
        }

        $scheduleManager->generateSchedules();
        $scheduleManager->cleanup();

        $processManager = Mage::getModel('aoe_scheduler/processManager'); /* @var $processManager Aoe_Scheduler_Model_ProcessManager */
        $processManager->checkRunningJobs();
    }

    /**
     * Process cron queue for tasks marked as 'always'
     *
     * @param Varien_Event_Observer $observer
     */
    public function dispatchAlways(Varien_Event_Observer $observer)
    {
        if (!Mage::getStoreConfigFlag('system/cron/enable')) {
            return;
        }

        $processManager = Mage::getModel('aoe_scheduler/processManager'); /* @var $processManager Aoe_Scheduler_Model_ProcessManager */
        $processManager->processKillRequests();

        $scheduleManager = Mage::getModel('aoe_scheduler/scheduleManager'); /* @var $scheduleManager Aoe_Scheduler_Model_ScheduleManager */

        /* @var Aoe_Scheduler_Helper_Data $helper */
        $helper = Mage::helper('aoe_scheduler');

        $includeJobs = array_filter(array_map('trim', (array)$observer->getIncludeJobs()));
        $excludeJobs = array_filter(array_map('trim', (array)$observer->getExcludeJobs()));

        $includeGroups = array_filter(array_map('trim', (array)$observer->getIncludeGroups()));
        $includeJobs = $helper->addGroupJobs($includeJobs, $includeGroups);

        $excludeGroups = array_filter(array_map('trim', (array)$observer->getExcludeGroups()));
        $excludeJobs = $helper->addGroupJobs($excludeJobs, $excludeGroups);

        /* @var Varien_Data_Collection $allJobs */
        $allJobs = Mage::getModel('aoe_scheduler/job_factory')->getAllJobs($includeJobs, $excludeJobs);
        foreach ($allJobs as $job) {
            /* @var $job Aoe_Scheduler_Model_Job_Abstract */
            if ($job->isAlwaysTask() && $job->getRunModel()) {
                $schedule = $scheduleManager->getScheduleForAlwaysJob($job->getJobCode());
                if ($schedule !== false) {
                    $schedule->process();
                }
            }
        }
    }
}
