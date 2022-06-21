<?php

require_once 'library/CE/NE_MailGateway.php';
require_once 'modules/clients/models/UserPackageGateway.php';
require_once 'modules/billing/models/Invoice.php';
require_once 'modules/admin/models/ServicePlugin.php';
require_once 'modules/admin/models/StatusAliasGateway.php';

class PluginAutoterminate extends ServicePlugin
{
    public $hasPendingItems = true;
    protected $featureSet = 'products';

    function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => lang('Auto Terminate'),
            ),
            lang('Enabled') => array(
                'type'        => 'yesno',
                'description' => lang('When enabled, overdue packages will be terminated and removed from the server.'),
                'value'       => '0',
            ),
            lang('Email Notifications') => array(
                'type'        => 'textarea',
                'description' => lang('When a package requires manual termination you will be notified at this email address. If packages are terminated when this service is run, a summary email will be sent to this address.'),
                'value'       => '',
            ),
            lang('Days Overdue Before Terminating') => array(
                'type'        => 'text',
                'description' => lang('Only terminate packages that are this many days overdue.'),
                'value'       => '7',
            ),
            lang('Run schedule - Minute') => array(
                'type'        => 'text',
                'description' => lang('Enter number, range, list or steps'),
                'value'       => '0',
                'helpid'      => '8',
            ),
            lang('Run schedule - Hour') => array(
                'type'        => 'text',
                'description' => lang('Enter number, range, list or steps'),
                'value'       => '0',
            ),
            lang('Run schedule - Day') => array(
                'type'        => 'text',
                'description' => lang('Enter number, range, list or steps'),
                'value'       => '*',
            ),
            lang('Run schedule - Month') => array(
                'type'        => 'text',
                'description' => lang('Enter number, range, list or steps'),
                'value'       => '*',
            ),
            lang('Run schedule - Day of the week') => array(
                'type'        => 'text',
                'description' => lang('Enter number in range 0-6 (0 is Sunday) or a 3 letter shortcut (e.g. sun)'),
                'value'       => '*',
            ),
            lang('Notified Package List') => array(
                'type'        => 'hidden',
                'description' => lang('Used to store package IDs of manually terminated packages whose email has already been sent.'),
                'value'       => ''
            )
        );

        return $variables;
    }

    function execute()
    {
        $gateway = new UserPackageGateway($this->user);
        $messages = [];
        $newPreEmailed = [];
        $autoTerminated = [];
        $manualTerminate = [];
        $preEmailed = unserialize($this->settings->get('plugin_autoterminate_Notified Package List'));
        $dueDays = $this->settings->get('plugin_autoterminate_Days Overdue Before Terminating');

        if ($dueDays != 0) {

            $overdueArray = $this->_getOverduePackagesReadyToTerminate();

            foreach ($overdueArray as $packageId => $dueDate) {
                $userPackage = new UserPackage($packageId, array(), $this->user);
                $user = new User($userPackage->getCustomerId(), $this->user);

                if ($gateway->hasServerPlugin($userPackage->getCustomField("Server Id"), $pluginName)) {
                    $errors = false;

                    try {
                        $userPackage->cancel(true, true);

                        if ( $user->getTotalNonCancelledPackages() == 0 ) {
                            $user->cancel();
                            $user->save();
                        }

                    } catch (Exception $ex) {
                        $errors = true;
                    }

                    if ($errors) {
                        $newPreEmailed[] = $userPackage->getID();

                        if (!is_array($preEmailed) || !in_array($userPackage->getID(), $preEmailed)) {
                            $manualTerminate[] = $userPackage->getID();
                        }
                    } else {
                        $autoTerminated[] = $userPackage->getID();

                        $packageLog = Package_EventLog::newInstance(false, $userPackage->getCustomerId(), $packageId, PACKAGE_EVENTLOG_AUTOTERMINATED, 0, $userPackage->getReference(true));
                        $packageLog->save();
                    }
                } elseif (!is_array($preEmailed) || !in_array($userPackage->getID(), $preEmailed)) {
                    $manualTerminate[] = $userPackage->getID();
                    $newPreEmailed[] = $userPackage->getID();
                } else {
                    $newPreEmailed[] = $userPackage->getID();
                }
            }

            $sendSummary = false;
            $body = $this->user->lang("Auto Terminate Service Summary")."\n\n";

            if (count($autoTerminated) > 0) {
                $sendSummary = true;
                $body .= $this->user->lang("Terminated").":\n\n";

                foreach ($autoTerminated as $id) {
                    $domain = new UserPackage($id, array(), $this->user);
                    $user = new User($domain->CustomerId);
                    $body .= $user->getFullName()." => ".$domain->getReference(true)."\n";
                }

                $body .= "\n";
            }

            if (count($manualTerminate) > 0) {
                $sendSummary = true;
                $body .= $this->user->lang("Requires Manual Termination").":\n\n";

                foreach ($manualTerminate as $id) {
                    $domain = new UserPackage($id, array(), $this->user);
                    $user = new User($domain->CustomerId);
                    $body .= $user->getFullName()." => ".$domain->getReference(true)."\n";
                }
            }

            if ($sendSummary && $this->settings->get('plugin_autoterminate_Email Notifications') != "") {
                $mailGateway = new NE_MailGateway();
                $destinataries = explode("\r\n", $this->settings->get('plugin_autoterminate_Email Notifications'));

                foreach ($destinataries as $destinatary) {
                    if ($destinatary != '') {
                        $mailGateway->mailMessageEmail(
                            $body,
                            $this->settings->get('Support E-mail'),
                            $this->settings->get('Company Name'),
                            $destinatary,
                            false,
                            $this->user->lang("Auto Terminate Service Summary")
                        );
                    }
                }
            }

            // Store the new notified list
            array_unshift($messages, $this->user->lang('%s package(s) terminated', count($autoTerminated)));
        }

        $this->settings->updateValue("plugin_autoterminate_Notified Package List", serialize($newPreEmailed));
        return $messages;
    }

    function pendingItems()
    {
        $gateway = new UserPackageGateway($this->user);
        $overdueArray = $this->_getOverduePackagesReadyToTerminate();
        $returnArray = array();
        $returnArray['data'] = array();

        foreach ($overdueArray as $packageId => $dueDate) {
            $domain = new UserPackage($packageId, array(), $this->user);
            $user = new User($domain->CustomerId);

            if ($gateway->hasServerPlugin($domain->getCustomField("Server Id"), $pluginName)) {
                $auto = "No";
            } else {
                $auto = "<span style=\"color:red\"><b>Yes</b></span>";
            }

            $tmpInfo = array();
            $tmpInfo['customer'] = '<a href="index.php?fuse=clients&controller=userprofile&view=profilecontact&frmClientID=' . $user->getId() . '">' . $user->getFullName() . '</a>';
            $tmpInfo['package_type'] = $domain->getProductGroupName();

            if ($domain->getProductType() == 3) {
                $tmpInfo['package'] = $domain->getProductGroupName();
            } else {
                $tmpInfo['package'] = $domain->getProductName();
            }

            $tmpInfo['domain'] = '<a href="index.php?fuse=clients&controller=userprofile&view=profileproduct&selectedtab=groupinfo&frmClientID=' . $user->getId() . '&id=' . $domain->getId() . '">' . $domain->getReference(true) . '</a>';
            $tmpInfo['date'] = date($this->settings->get('Date Format'), $dueDate);
            $tmpInfo['manual'] = $auto;
            $returnArray['data'][] = $tmpInfo;
        }

        $returnArray["totalcount"] = count($returnArray['data']);
        $returnArray['headers'] = array(
            $this->user->lang('Client'),
            $this->user->lang('Package Type'),
            $this->user->lang('Package Name'),
            $this->user->lang('Package'),
            $this->user->lang('Due Date'),
            $this->user->lang('Requires Manual Termination?'),
        );

        return $returnArray;
    }

    function output()
    {
    }

    function dashboard()
    {
        $overdueArray = $this->_getOverduePackagesReadyToTerminate();
        $autoTerminate = 0;
        $manualTerminate = 0;
        $gateway = new UserPackageGateway($this->user);

        foreach ($overdueArray as $packageId => $dueDate) {
            $userPackage = new UserPackage($packageId, array(), $this->user);

            if ($gateway->hasServerPlugin($userPackage->getCustomField("Server Id"), $pluginName)) {
                $autoTerminate++;
            } else {
                $manualTerminate++;
            }
        }

        $message = $this->user->lang('Number of packages pending auto termination: %d', $autoTerminate);
        $message .= "<br>";
        $message .= $this->user->lang('Number of packages requiring manual termination: %d', $manualTerminate);

        return $message;
    }

    function _getOverduePackagesReadyToTerminate()
    {
        $query = "SELECT id FROM invoice WHERE status IN (0, 5) AND billdate < DATE_SUB( NOW() , INTERVAL ? DAY ) ORDER BY billdate ASC";
        $result = $this->db->query($query, @$this->settings->get('plugin_autoterminate_Days Overdue Before Terminating'));
        $overduePackages = array();
        $overdueCustomers = array();
        $statusGateway = StatusAliasGateway::getInstance($this->user);

        while ($row = $result->fetch()) {
            $invoice = new Invoice($row['id']);
            $user = new User($invoice->getUserID());

            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                if ($invoiceEntry->AppliesTo() != 0) {
                    // Found an overdue package, add it to the list
                    if (!in_array($invoiceEntry->AppliesTo(), array_keys($overduePackages))) {
                        $package = new UserPackage($invoiceEntry->AppliesTo(), array(), $this->user);

                        if (!$statusGateway->isSuspendedPackageStatus($package->status)) {
                            continue;
                        }

                        // ignore this user package, as we are set to override the autosuspend.
                        if ($package->getCustomField('Override AutoSuspend') == 1) {
                            continue;
                        }

                        $overduePackages[$invoiceEntry->AppliesTo()] = $invoice->getDate('timestamp');
                    }
                }
            }
        }
        asort($overduePackages);
        return $overduePackages;
    }
}