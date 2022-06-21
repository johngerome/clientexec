<?php
/**
 * Exports data from a WHMCS installation to ClientExec format
 *
 */

error_reporting(0);

class Database
{
    protected $_db;
    protected $_numRows;
    public $skipFatalError = false;

    public function closeConnection ()
    {
        if (!mysqli_close($this->_db)) {
            throw new Exception('Unable to close connection.');
        }
    }

    public function connect ($hostname, $username, $password, $database, $port)
    {
        /* You should enable error reporting for mysqli before attempting to make a connection */
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        if ($hostname === '') {
            $hostname = 'localhost';
        }

        if ($port !== ''){
            if (!($this->_db = mysqli_connect($hostname, $username, $password, $database, $port))) {
                throw new Exception('Unable to connect to database: ' . mysqli_connect_error() . ' Error Code: ' . mysqli_connect_errno());
            }
        } else {
            if (!($this->_db = mysqli_connect($hostname, $username, $password, $database))) {
                throw new Exception('Unable to connect to database: ' . mysqli_connect_error() . ' Error Code: ' . mysqli_connect_errno());
            }
        }

        $this->setSkipFatalError(true);
        $this->query("SET session sql_mode=''");
        $this->setSkipFatalError(false);
    }

    public function setSkipFatalError($skipFatalError)
    {
        $this->skipFatalError = $skipFatalError;
    }

    public function getDb ()
    {
        return $this->_db;
    }

    public function getNumRows ()
    {
        return $this->_numRows;
    }

    public function packagePriceByCycle ($row)
    {
        switch(trim(str_replace(array(' ', '-', '_'), '', strtolower($row['billingcycle'])))) {
            case 'freeaccount':
            case 'onetime':
                $price = 0;
                break;
            case 'monthly':
                $price = $row['monthly'];
                break;
            case 'quarterly':
                $price = $row['quarterly'];
                break;
            case 'semiannually':
                $price = $row['semiannually'];
                break;
            case 'annually':
                $price = $row['annually'];
                break;
            case 'biennially':
                $price = $row['biennially'];
                break;
            case 'triennially':
                $price = $row['triennially'];
                break;
            default:
                if (empty($row['billingcycle'])) {
                    throw new Exception('Unable to get the price by empty cycle.');
                } else {
                    throw new Exception("Unable to get the price by cycle '{$row['billingcycle']}'.");
                }
        }

        return $price;
    }

    public function query ($query)
    {
        $result = mysqli_query($this->getDb(), $query) or die(mysqli_error($this->getDb()));

        if ($result === false) {
            if (!$this->skipFatalError) {
                throw new Exception('Unable to execute the query. ' . mysqli_error($this->getDb()));
            }
        } elseif ($result === true) {
            $affectedRows = mysqli_affected_rows($this->getDb());

            return $affectedRows;
        } elseif ($result) {
            $rows = array();
            $this->_numRows = mysqli_num_rows($result);

            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }

            return $rows;
        } else {
            if (!$this->skipFatalError) {
                throw new Exception('Unexpected return from query.');
            }
        }
    }

    public function setDb ($value)
    {
        $this->_db = $value;
    }
}

abstract class Exporter extends Database
{
    public $encryptionHash = '';

    /**
     * Header columns names.
     */
    protected $_columns = array(
        'domains' => array('clientid', 'activateddate', 'domainname', 'recurring', 'registrationperiod', 'status', 'nextduedate', 'registrar', 'subscription_id'),
        'hosting' => array('id', 'clientid', 'username', 'plan', 'regdate', 'domain', 'status', 'nextinvoicedate', 'paymentterm', 'price', 'server', 'acctproperties', 'coupon', 'subscription_id'),
        'hosting_addons' => array('clientid', 'packageid', 'addonid', 'addonoptionid', 'nextinvoicedate', 'paymentterm', 'price', 'name', 'subscription_id', 'quantity'),
        'invoices' => array('id', 'clientid', 'amount', 'datedue', 'datepaid', 'description', 'detail', 'tax', 'subtotal', 'status'),
        'invoices_entries' => array('id', 'clientid', 'invoiceid', 'relid', 'amount', 'tax', 'datedue', 'detail', 'description'),
        'invoices_transaction' => array('id', 'invoiceid', 'accepted', 'response', 'transactiondate', 'transactionid', 'action', 'last4', 'amount'),
        'packages' => array('id', 'name', 'description', 'packagetype', 'tax', 'pricing', 'servers'),
        'packages_addons' => array('id', 'name', 'description'),
        'packages_addons_options' => array('id', 'packageaddonid', 'detail', 'pricing'),
        'packages_groups' => array('id', 'description', 'insignup', 'name', 'type', 'canDelete', 'groupOrder', 'style', 'advanced' ),
        'servers' => array('id', 'name', 'hostname', 'ipaddress', 'assignedips', 'statusaddress', 'maxaccounts', 'type', 'username', 'password', 'accesshash', 'secure', 'nameserver1', 'nameserver1ip', 'nameserver2', 'nameserver2ip', 'nameserver3', 'nameserver3ip', 'nameserver4', 'nameserver4ip'),
        'users' => array('id', 'firstname', 'lastname', 'address', 'email', 'city', 'state', 'zip', 'phone', 'country', 'company', 'status', 'language', 'cardnum', 'expdate', 'billing_profile_id', 'password', 'balance'),
        'departments' => array ('id', 'name' ),
        'tickets' => array ( 'id', 'userid', 'date', 'title', 'message', 'status', 'urgency', 'name', 'email' ),
        'ticket_logs' => array ( 'id', 'tid', 'userid', 'date', 'message', 'email', 'is_staff'),
        'coupons' => array ('id', 'code', 'type', 'recurring', 'value', 'appliesto', 'startdate', 'expirationdate'),
        'staff' => array('id', 'firstname', 'lastname', 'email', 'status', 'password'),
        'alternate_accounts' => array('userid', 'email', 'sendnotifications', 'sendinvoice', 'sendsupport'),
        'tax_rule' => array('countryiso', 'state', 'tax', 'vat', 'name', 'level', 'compound'),
        'kb_categories' => array('id', 'parent_id', 'name', 'description', 'staffonly', 'catid', 'language'),
        'kb_articles' => array('id', 'title', 'content', 'access', 'myorder', 'categoryid', 'tags', 'parentid', 'language'),
        'kb_articles_files' => array('id', 'filename', 'dateadded', 'filekey'),
        'canned_response' => array('name', 'response'),
        'smtp' => array('host', 'username', 'password', 'port'),
        'email' => array('id', 'userid', 'subject', 'content_encrypted', 'content', 'date', 'to', 'sender', 'fromName'),
        'credit_history' => array('date', 'user_id', 'subject', 'description', 'amount')
    );

    protected $_columnsBuffer = array();

    protected $_filename;

    protected $_isUtf8 = false;

    /**
     * Lines buffer. For performance propulse.
     */
    protected $_linesBuffer = array();

    protected $_mysqlBufferLimit = 50;

    /**
     * Zlib file pointer
     */
    protected $_zp;

    function __construct ()
    {
        $this->_setupFile();
    }

    protected function _addColumn ($value, $skipEscaping = false)
    {
        $value = str_replace("\r\n", "\n", $value);

        if (!$this->_isUtf8) {
            $value = utf8_encode($value);
        }

        if (!$skipEscaping) {
            $value = json_encode($value);
            $value = str_replace(',', '\c', $value);
        }

        $this->_columnsBuffer[] = $value;
    }

    protected function _addHeader ($section)
    {
        if (!array_key_exists($section, $this->_columns)) {
            throw new Exception("Invalid section '{$section}'.");
        }

        $this->_addLine("; {$section}");

        foreach ($this->_columns[$section] as $column) {
            $this->_addColumn($column, true);
        }

        $this->_addLine();
    }

    protected function _addLine ($lineContents = null)
    {
        if ($lineContents === null) {
            if (count($this->_columnsBuffer) < 1) {
                throw new Exception('Cannot add a line without columns.');
            }

            $lineContents = implode(',', $this->_columnsBuffer);
            $this->_columnsBuffer = array();
        }

        $lineContents = trim($lineContents);

        if (empty($lineContents)) {
            throw new Exception('Cannot add an empty line to the file.');
        }

        $lineContents .= "\n";

        $this->_linesBuffer[] = $lineContents;
    }

    protected function _setupFile ()
    {
        $this->_filename = tempnam(sys_get_temp_dir(), 'PHP');

        if (!file_exists($this->_filename)) {
            // Attempt to create the file
            if (!touch($this->_filename)) {
                throw new Exception('Unable to create the temporary file.');
            }
        }

        if (!is_writable($this->_filename)) {
            // Attempt to give write permissions
            if (!chmod($this->_filename, 0666)) {
                throw new Exception('Unable to set temporary file permissions.');
            }
        }
    }

    public function export ()
    {
        $this->_addHeader('staff');
        $this->exportStaff();
        $this->_addHeader('users');
        $this->exportUsers();
        $this->_addHeader('servers');
        $this->exportServers();
        $this->_addHeader('packages_groups');
        $this->exportPackagesGroups();
        $this->_addHeader('packages');
        $this->exportPackages();
        $this->_addHeader('packages_addons');
        $this->exportPackagesAddons();
        $this->_addHeader('packages_addons_options');
        $this->exportPackagesAddonsOptions();
        $this->_addHeader('coupons');
        $this->exportCoupons();
        $this->_addHeader('hosting');
        $this->exportHosting();
        $this->_addHeader('invoices');
        $this->exportInvoices();
        $this->_addHeader('invoices_entries');
        $this->exportInvoicesEntries();
        $this->_addHeader('invoices_transaction');
        $this->exportInvoicesTransaction();
        $this->_addHeader('domains');
        $this->exportDomains();
        $this->_addHeader('hosting_addons');
        $this->exportHostingAddons();
        $this->_addHeader('departments');
        $this->exportDepartments();
        $this->_addHeader('tickets');
        $this->exportTickets();
        $this->_addHeader('ticket_logs');
        $this->exportTicketLogs();
        $this->_addHeader('alternate_accounts');
        $this->exportAlternateAccounts();
        $this->_addHeader('tax_rule');
        $this->exportTaxRule();
        $this->_addHeader('kb_categories');
        $this->exportKBCategories();
        $this->_addHeader('kb_articles');
        $this->exportKBArticles();
        $this->_addHeader('kb_articles_files');
        $this->exportKBArticlesFiles();
        $this->_addHeader('canned_response');
        $this->exportCannedResponse();
        $this->_addHeader('smtp');
        $this->exportSMTP();
        $this->_addHeader('email');
        $this->exportEmail();
        $this->_addHeader('credit_history');
        $this->exportCreditHistory();
    }

    abstract public function exportDomains();
    abstract public function exportHosting();
    abstract public function exportHostingAddons();
    abstract public function exportInvoices();
    abstract public function exportInvoicesEntries();
    abstract public function exportInvoicesTransaction();
    abstract public function exportPackages();
    abstract public function exportPackagesAddons();
    abstract public function exportPackagesAddonsOptions();
    abstract public function exportPackagesGroups();
    abstract public function exportUsers();
    abstract public function exportServers();
    abstract public function exportDepartments();
    abstract public function exportTickets();
    abstract public function exportTicketLogs();
    abstract public function exportCoupons();
    abstract public function exportStaff();
    abstract public function exportAlternateAccounts();
    abstract public function exportTaxRule();
    abstract public function exportKBCategories();
    abstract public function exportKBArticles();
    abstract public function exportKBArticlesFiles();
    abstract public function exportCannedResponse();
    abstract public function exportSMTP();
    abstract public function exportEmail();
    abstract public function exportCreditHistory();

    public function getBuffer ()
    {
        return $this->_linesBuffer;
    }
}

class WHMCS_Exporter extends Exporter
{
    protected $_isUtf8 = false;

    public function cycle2ce ($cycle)
    {
        switch(trim(str_replace(array(' ', '-', '_'), '', strtolower($cycle)))) {
            case 'free':
            case 'freeaccount':
            case 'onetime':
                $ce_cycle = '0m';
                break;
            case 'monthly':
                $ce_cycle = '1m';
                break;
            case 'quarterly':
                $ce_cycle = '3m';
                break;
            case 'semiannually':
                $ce_cycle = '6m';
                break;
            case 'annually':
                $ce_cycle = '1y';
                break;
            case 'biennially':
                $ce_cycle = '2y';
                break;
            case 'triennially':
                $ce_cycle = '3y';
                break;
            default:
                if (empty($cycle)) {
                    throw new Exception('Unable to convert empty cycle.');
                } else {
                    throw new Exception("Unable to convert the cycle '{$cycle}'.");
                }
        }

        return $ce_cycle;
    }

    public function exportDomains ()
    {
        $this->query("SHOW COLUMNS FROM `tbldomains` LIKE 'subscriptionid'");
        $existssubscriptionid = ($this->getNumRows())? true : false;

        $subscriptionidField = "'' AS subscriptionid"; 

        if ($existssubscriptionid) {
            $subscriptionidField = 'subscriptionid'; 
        }

        $offset = 0;

        do {
            $query = "SELECT userid, registrationdate, domain, recurringamount, registrationperiod, status, nextinvoicedate, registrar, paymentmethod, {$subscriptionidField} FROM tbldomains LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $row['registrationdate'] = date('Y-m-d', strtotime($row['registrationdate']));
                $row['status'] = $this->packageStatus2ce($row['status']);
                $row['nextinvoicedate'] = date('Y-m-d', strtotime($row['nextinvoicedate']));

                $subscriptionid = '';

                if (strpos($row['paymentmethod'], 'paypal') !== false) {
                    $subscriptionid = $row['subscriptionid'];
                }

                $moduleName = trim(str_replace(array(' ', '-', '_'), '', strtolower($row['registrar'])));

                if ($moduleName == 'synergywholesaledomains') {
                    $moduleName = 'synergywholesale';
                }

                $this->_addColumn($row['userid']);
                $this->_addColumn($row['registrationdate']);
                $this->_addColumn($row['domain']);
                $this->_addColumn($row['recurringamount']);
                $this->_addColumn($row['registrationperiod'].'y');
                $this->_addColumn($row['status']);
                $this->_addColumn($row['nextinvoicedate']);
                $this->_addColumn($moduleName);
                $this->_addColumn($subscriptionid);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportCoupons()
    {
        $offset = 0;

        do {
            $query = "SELECT id, code, type, recurring, value, appliesto, startdate, expirationdate FROM tblpromotions LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['code']);
                $this->_addColumn($row['type']);
                $this->_addColumn($row['recurring']);
                $this->_addColumn($row['value']);
                $this->_addColumn($row['appliesto']);
                $this->_addColumn($row['startdate']);
                $this->_addColumn($row['expirationdate']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }


    public function exportHosting ()
    {
        $this->query("SHOW COLUMNS FROM `tblhosting` LIKE 'subscriptionid'");
        $existssubscriptionid = ($this->getNumRows())? true : false;

        $subscriptionidField = "'' AS subscriptionid"; 

        if ($existssubscriptionid) {
            $subscriptionidField = 'h.subscriptionid'; 
        }

        $offset = 0;

        do {
            $query = "SELECT h.id, h.userid, h.username, h.packageid, h.regdate, h.domain, h.domainstatus, h.nextinvoicedate, h.billingcycle, h.server, pri.monthly, pri.quarterly, pri.semiannually, pri.annually, pri.biennially, pri.triennially, h.promoid, h.paymentmethod, {$subscriptionidField} FROM tblhosting h, tblpricing pri WHERE pri.type='product' AND pri.relid=h.packageid LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);
            $numRows = $this->getNumRows();

            foreach ($result as $row) {
                $domainstatus = $this->packageStatus2ce($row['domainstatus']);
                $billingcycle = $this->cycle2ce($row['billingcycle']);
                $packagePrice = $this->packagePriceByCycle($row);

                $subscriptionid = '';

                if (strpos($row['paymentmethod'], 'paypal') !== false) {
                    $subscriptionid = $row['subscriptionid'];
                }

                $acctProperties = '';

                if (isset($row['server']) && $row['server'] != '' && $row['server'] != 0) {
                    $query2 = "SELECT type FROM tblservers WHERE id = ".$row['server'];
                    $result2 = $this->query($query2);

                    $hostingPlugin = '';

                    foreach ($result2 as $result2Values) {
                        $hostingPlugin = trim(str_replace(array(' ', '-', '_'), '', strtolower($result2Values['type'])));
                    }

                    switch ($hostingPlugin) {
                        case 'virtualizor':
                            //vpsid field id
                            $queryvpsid = "SELECT id FROM tblcustomfields WHERE fieldname = 'vpsid' AND type = 'product' AND relid = ".$row['packageid'];
                            $resultvpsid = $this->query($queryvpsid);

                            $vpsidFieldId = '';

                            foreach ($resultvpsid as $resultvpsidValues) {
                                $vpsidFieldId = $resultvpsidValues['id'];
                            }
                            //vpsid field id

                            if ($vpsidFieldId != '') {
                                $query3 = "SELECT value FROM tblcustomfieldsvalues WHERE fieldid = ".$vpsidFieldId." AND relid = ".$row['id'];
                                $result3 = $this->query($query3);

                                foreach ($result3 as $result3Values) {
                                    $acctProperties = $result3Values['value'];
                                }
                            }

                            break;
                    }
                }

                $this->_addColumn($row['id']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['username']);
                $this->_addColumn($row['packageid']);
                $this->_addColumn($row['regdate']);
                $this->_addColumn($row['domain']);
                $this->_addColumn($domainstatus);
                $this->_addColumn($row['nextinvoicedate']);
                $this->_addColumn($billingcycle);
                $this->_addColumn($packagePrice);
                $this->_addColumn($row['server']);
                $this->_addColumn($acctProperties);
                $this->_addColumn($row['promoid']);
                $this->_addColumn($subscriptionid);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($numRows >= 1);
    }

    public function exportHostingAddons ()
    {
        $this->query("SHOW COLUMNS FROM `tblhostingconfigoptions` LIKE 'qty'");
        $existsqty = ($this->getNumRows())? true : false;

        $qtyField = '1 AS qty'; 

        if ($existsqty) {
            $qtyField = 'opt.qty'; 
        }

        $this->query("SHOW COLUMNS FROM `tblhosting` LIKE 'subscriptionid'");
        $existssubscriptionid = ($this->getNumRows())? true : false;

        $subscriptionidField = "'' AS subscriptionid"; 

        if ($existssubscriptionid) {
            $subscriptionidField = 'pkg.subscriptionid'; 
        }

        $offset = 0;

        do {
            $query = "SELECT pkg.userid, opt.relid, opt.configid, opt.optionid, {$qtyField}, pkg.billingcycle, pkg.nextinvoicedate, pri.monthly, pri.quarterly, pri.semiannually, pri.annually, pri.biennially, pri.triennially, optsub.optionname, pkg.paymentmethod, {$subscriptionidField} FROM tblhosting pkg, tblhostingconfigoptions opt, tblpricing pri, tblproductconfigoptionssub optsub WHERE pkg.id = opt.relid AND pri.type='configoptions' AND pri.relid=opt.id AND optsub.id= opt.optionid LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $billingcycle = $this->cycle2ce($row['billingcycle']);
                $packagePrice = $this->packagePriceByCycle($row);

                $subscriptionid = '';

                if (strpos($row['paymentmethod'], 'paypal') !== false) {
                    $subscriptionid = $row['subscriptionid'];
                }

                $this->_addColumn($row['userid']);
                $this->_addColumn($row['relid']);
                $this->_addColumn($row['configid']);
                $this->_addColumn($row['optionid']);
                $this->_addColumn($row['nextinvoicedate']);
                $this->_addColumn($billingcycle);
                $this->_addColumn($packagePrice);
                $this->_addColumn($row['optionname']);
                $this->_addColumn($subscriptionid);
                $this->_addColumn($row['qty']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);


        //New Table
        $this->query("SHOW TABLES LIKE 'tblhostingaddons'");
        $existstblhostingaddons = ($this->getNumRows())? true : false;

        if ($existstblhostingaddons) {
            $this->query("SHOW COLUMNS FROM `tblhostingaddons` LIKE 'qty'");
            $existsqty = ($this->getNumRows())? true : false;

            $qtyField = '1 AS qty'; 

            if ($existsqty) {
                $qtyField = 'qty'; 
            }

            $this->query("SHOW COLUMNS FROM `tblhostingaddons` LIKE 'subscriptionid'");
            $existssubscriptionid = ($this->getNumRows())? true : false;

            $subscriptionidField = "'' AS subscriptionid"; 

            if ($existssubscriptionid) {
                $subscriptionidField = 'subscriptionid'; 
            }

            $offset = 0;

            do {
                $query = "SELECT userid, hostingid, addonid, nextinvoicedate, billingcycle, recurring, name, paymentmethod, {$subscriptionidField}, {$qtyField} FROM tblhostingaddons LIMIT {$offset}, {$this->_mysqlBufferLimit}";
                $result = $this->query($query);

                foreach ($result as $row) {
                    $billingcycle = $this->cycle2ce($row['billingcycle']);
                    $packagePrice = $row['recurring'];

                    $subscriptionid = '';

                    if (strpos($row['paymentmethod'], 'paypal') !== false) {
                        $subscriptionid = $row['subscriptionid'];
                    }

                    $this->_addColumn($row['userid']);
                    $this->_addColumn($row['hostingid']);
                    $this->_addColumn($row['addonid']);
                    $this->_addColumn(0);
                    $this->_addColumn($row['nextinvoicedate']);
                    $this->_addColumn($billingcycle);
                    $this->_addColumn($packagePrice);
                    $this->_addColumn($row['name']);
                    $this->_addColumn($subscriptionid);
                    $this->_addColumn($row['qty']);
                    $this->_addLine();
                }

                $offset += $this->_mysqlBufferLimit;
            } while ($this->getNumRows() >= 1);
        }
    }

    public function exportInvoices ()
    {
        $offset = 0;

        do {
            $query = "SELECT id, userid, total, duedate, datepaid, notes, tax, subtotal, status FROM tblinvoices LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $row['duedate'] = date('Y-m-d', strtotime($row['duedate']));
                $row['notes'] = str_replace('"', '\"', $row['notes']);
                $row['status'] = $this->invoiceStatus2ce($row['status']);

                if ($row['datepaid'] != 0) {
                    $row['datepaid'] = date('Y-m-d', strtotime($row['datepaid']));
                } else {
                    $row['datepaid'] = 0;
                }

                $this->_addColumn($row['id']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['total']);
                $this->_addColumn($row['duedate']);
                $this->_addColumn($row['datepaid']);
                $this->_addColumn('Imported Invoice'.(($row['id'] != '') ? ' #'.$row['id'] : ''));
                $this->_addColumn($row['notes']);
                $this->_addColumn($row['tax']);
                $this->_addColumn($row['subtotal']);
                $this->_addColumn($row['status']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportInvoicesEntries ()
    {
        $offset = 0;

        do {
            $query = "SELECT ie.id, ie.userid, ie.invoiceid, ie.relid, ie.amount, ie.taxed, ie.notes, ie.description, i.duedate FROM tblinvoiceitems ie, tblinvoices i WHERE ie.invoiceid=i.id LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                if ($row['duedate'] != '0000-00-00') {
                    $row['duedate'] = date('Y-m-d', strtotime($row['duedate']));
                }
                $this->_addColumn($row['id']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['invoiceid']);
                $this->_addColumn($row['relid']);
                $this->_addColumn($row['amount']);
                $this->_addColumn($row['taxed']);
                $this->_addColumn($row['duedate']);
                $this->_addColumn($row['notes']);
                $this->_addColumn($row['description']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportInvoicesTransaction ()
    {
        $offset = 0;

        do {
            $query = "SELECT id, invoice_id, completed, description, created_at, transaction_id, amount FROM tbltransaction_history LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['invoice_id']);
                $this->_addColumn($row['completed']);
                $this->_addColumn($row['description']);
                $this->_addColumn($row['created_at']);
                $this->_addColumn($row['transaction_id']);
                $this->_addColumn('none');
                $this->_addColumn('NA');
                $this->_addColumn($row['amount']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportPackages ()
    {
        $offset = 0;

        do {
            $query = "SELECT pro.id, pro.gid, pro.name, pri.monthly, pri.quarterly, pri.semiannually, pri.annually, pri.biennially, pri.triennially, pro.description, pro.type, pro.tax, pri.msetupfee, pri.qsetupfee, pri.ssetupfee, pri.asetupfee, pri.bsetupfee, pri.tsetupfee, pro.servergroup FROM tblproducts pro, tblpricing pri WHERE pro.id = pri.relid AND pri.type = 'product' GROUP BY  pro.id LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);
            $numRows = $this->getNumRows();

            foreach ($result as $row) {
                $pricing = array(
                    '1m' => array(
                        'price' => $row['monthly'],
                        'setup' => $row['msetupfee']
                    ),
                    '3m' => array(
                        'price' => $row['quarterly'],
                        'setup' => $row['qsetupfee']
                    ),
                    '6m' => array(
                        'price' => $row['semiannually'],
                        'setup' => $row['ssetupfee']
                    ),
                    '1y' => array(
                        'price' => $row['annually'],
                        'setup' => $row['asetupfee']
                    ),
                    '2y' => array(
                        'price' => $row['biennially'],
                        'setup' => $row['bsetupfee']
                    ),
                    '3y' => array(
                        'price' => $row['triennially'],
                        'setup' => $row['tsetupfee']
                    )
                );

                $query2 = "SELECT serverid FROM tblservergroupsrel WHERE groupid = ".$row['servergroup'];
                $result2 = $this->query($query2);

                $servers = array();

                foreach ($result2 as $result2Values) {
                    $servers[] = $result2Values['serverid'];
                }

                $row['description'] = str_replace('"', '\"', $row['description']);

                $this->_addColumn($row['id']);
                $this->_addColumn($row['name']);
                $this->_addColumn($row['description']);
                $this->_addColumn($row['gid']);
                $this->_addColumn($row['tax']);
                $this->_addColumn(serialize($pricing));
                $this->_addColumn(serialize($servers));
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($numRows >= 1);
    }

    public function exportPackagesAddons ()
    {
        $offset = 0;

        do {
            $query = "SELECT id, optionname FROM tblproductconfigoptions LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['optionname']);
                $this->_addColumn($row['optionname']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportPackagesAddonsOptions ()
    {
        $offset = 0;

        do {
            $query = "SELECT optsub.id, optsub.configid, optsub.optionname, pri.monthly, pri.quarterly, pri.semiannually, pri.annually, pri.biennially, pri.triennially, pri.msetupfee, pri.qsetupfee, pri.ssetupfee, pri.asetupfee, pri.bsetupfee, pri.tsetupfee FROM tblproductconfigoptionssub optsub, tblpricing pri WHERE pri.relid=optsub.id AND pri.type='configoptions' LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);


            //WHMCS allows to have a setup fee per billing cycle in an addon option
            //Clientexec allows to have only one setup fee for all the billing cycles in an addon option
            //To avoid lossing income, this importer will be using the greatest value in setup fees from WHMCS to Clientexec
            $setup = '';

            if ($row['msetupfee'] !== '') {
                $setup = $row['msetupfee'];
            }

            if ($setup === '' || ($row['qsetupfee'] !== '' && $row['qsetupfee'] > $setup)) {
                $setup = $row['qsetupfee'];
            }

            if ($setup === '' || ($row['ssetupfee'] !== '' && $row['ssetupfee'] > $setup)) {
                $setup = $row['ssetupfee'];
            }

            if ($setup === '' || ($row['asetupfee'] !== '' && $row['asetupfee'] > $setup)) {
                $setup = $row['asetupfee'];
            }

            if ($setup === '' || ($row['bsetupfee'] !== '' && $row['bsetupfee'] > $setup)) {
                $setup = $row['bsetupfee'];
            }

            if ($setup === '' || ($row['tsetupfee'] !== '' && $row['tsetupfee'] > $setup)) {
                $setup = $row['tsetupfee'];
            }

            foreach ($result as $row) {
                $pricing = array(
                    '0m' => array(
                        'price' => $setup
                    ),
                    '1m' => array(
                        'price' => $row['monthly']
                    ),
                    '3m' => array(
                        'price' => $row['quarterly']
                    ),
                    '6m' => array(
                        'price' => $row['semiannually']
                    ),
                    '1y' => array(
                        'price' => $row['annually']
                    ),
                    '2y' => array(
                        'price' => $row['biennially']
                    ),
                    '3y' => array(
                        'price' => $row['triennially']
                    )
                );

                $this->_addColumn($row['id']);
                $this->_addColumn($row['configid']);
                $this->_addColumn($row['optionname']);
                $this->_addColumn(serialize($pricing));
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportPackagesGroups ()
    {
        $offset = 0;

        do {
            $query = "SELECT id, name FROM tblproductgroups LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn('');
                $this->_addColumn(0);
                $this->_addColumn($row['name']);
                $this->_addColumn(1);
                $this->_addColumn(1);
                $this->_addColumn(1);
                $this->_addColumn('default');
                $this->_addColumn('');
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportStaff()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM `tbladmins` LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['firstname']);
                $this->_addColumn($row['lastname']);
                $this->_addColumn($row['email']);
                $this->_addColumn(1);
                $this->_addColumn($row['passwordhash']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportUsers ()
    {
        //New Table
        $this->query("SHOW TABLES LIKE 'tblpaymethods'");
        $existstblpaymethods = ($this->getNumRows())? true : false;

        $hash = $this->encryptionHash;

        $offset = 0;

        do {
            $query = "SELECT *, AES_DECRYPT(cardnum,md5(CONCAT('{$hash}', id ))) as realcardnum, AES_DECRYPT(expdate,md5(CONCAT('{$hash}', id ))) as realcardexp FROM tblclients LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);
            $numRows = $this->getNumRows();

            foreach ($result as $row) {
                $realcardnum = $row['realcardnum'];
                $realcardexp = $row['realcardexp'];
                $billing_profile_id = '';

                if ($realcardnum == '' && $existstblpaymethods) {
                    $realcardexp = '';

                    //The value 'BankAccount' for `payment_type` will be ignored, as Clientexec currently does not store Bank Accounts.
                    //If it does in the future, need to search the info for 'BankAccount' on the table `tblbankaccts`
                    $query2 = "SELECT id, payment_type, gateway_name FROM tblpaymethods WHERE userid = ".$row['id']." AND payment_type IN ('CreditCard', 'RemoteCreditCard') ORDER BY order_preference ASC LIMIT 1";
                    $result2 = $this->query($query2);

                    foreach ($result2 as $result2Values) {
                        $query3 = "SELECT card_data,last_four, DATE_FORMAT(expiry_date, '%m%Y') as realcardexp FROM tblcreditcards WHERE pay_method_id = ".$result2Values['id'];
                        $result3 = $this->query($query3);

                        foreach ($result3 as $result3Values) {
                            $aes = new \phpseclib\Crypt\AES(MODE_CBC);
                            $aes->setKey(md5($hash . $row['id']));
                            $aes->setKeyLength(256);
                            $aes->disablePadding();

                            $tempcardnum = $aes->decrypt(hex2bin($result3Values['card_data']));

                            $tempcardnum = str_replace(
                                array('\"', '"{', '}"'),
                                array('"', '{', '}'),
                                substr($tempcardnum, 0, strrpos($tempcardnum, '}') + 1)
                            );

                            $tempcardnum = json_decode($tempcardnum, true);

                            switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($result2Values['payment_type'])))) {
                                case 'creditcard':
                                    $realcardnum = $tempcardnum['cardNumber'];
                                    $realcardexp = $result3Values['realcardexp'];
                                    break;
                                case 'remotecreditcard':
                                    switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($result2Values['gateway_name'])))) {
                                        case 'stripe':
                                            $billing_profile_id = serialize(array('stripecheckout' => $tempcardnum['remoteToken']['customer'].'|'.$tempcardnum['remoteToken']['method']));
                                            break;
                                        case 'authorizecim':
                                            $tempcardnum = $tempcardnum['remoteToken'];
                                            $tempcardnum = explode(',', $tempcardnum);
                                            $billing_profile_id = serialize(array('authnetcim' => $tempcardnum[0]));
                                            break;
                                        case 'quantumvault':
                                            //ignored as we use different ids
                                            break;
                                    }

                                    break;
                            }
                        }
                    }
                }

                $row['status'] = $this->userStatus2ce($row['status']);

                $this->_addColumn($row['id']);
                $this->_addColumn($row['firstname']);
                $this->_addColumn($row['lastname']);
                $this->_addColumn($row['address1'] . $row['address2']);
                $this->_addColumn($row['email']);
                $this->_addColumn($row['city']);
                $this->_addColumn($row['state']);
                $this->_addColumn($row['postcode']);
                $this->_addColumn($row['phonenumber']);
                $this->_addColumn($row['country']);
                $this->_addColumn($row['companyname']);
                $this->_addColumn($row['status']);
                $this->_addColumn('English');
                $this->_addColumn($realcardnum);
                $this->_addColumn($realcardexp);
                $this->_addColumn($billing_profile_id);
                $this->_addColumn($row['password']);
                $this->_addColumn($row['credit']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($numRows >= 1);
    }

    public function exportServers ()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblservers LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['name']);
                $this->_addColumn($row['hostname']);
                $this->_addColumn($row['ipaddress']);
                $this->_addColumn($row['assignedips']);
                $this->_addColumn($row['statusaddress']);
                $this->_addColumn($row['maxaccounts']);
                $this->_addColumn($row['type']);
                $this->_addColumn($row['username']);
                $this->_addColumn($row['password']);
                $this->_addColumn($row['accesshash']);
                $this->_addColumn($row['secure']);
                $this->_addColumn($row['nameserver1']);
                $this->_addColumn($row['nameserver1ip']);
                $this->_addColumn($row['nameserver2']);
                $this->_addColumn($row['nameserver2ip']);
                $this->_addColumn($row['nameserver3']);
                $this->_addColumn($row['nameserver3ip']);
                $this->_addColumn($row['nameserver4']);
                $this->_addColumn($row['nameserver4ip']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportDepartments()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblticketdepartments LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['name']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportTickets()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tbltickets LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['date']);
                $this->_addColumn($row['title']);
                $this->_addColumn($row['message']);
                $this->_addColumn($this->ticketStatus2ce($row['status']));
                $this->_addColumn($this->ticketUrgency2ce($row['urgency']));
                $this->_addColumn($row['name']);
                $this->_addColumn($row['email']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportTicketLogs()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblticketreplies LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['tid']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['date']);
                $this->_addColumn($row['message']);
                $this->_addColumn($row['email']);
                $this->_addColumn(($row['admin'] != '' ? 1 : 0));
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportAlternateAccounts()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblcontacts LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $sendnotifications = ($row['generalemails'] == 1 || $row['productemails'] == 1 || $row['domainemails'] == 1)? '1' : '0';
                $this->_addColumn($row['userid']);
                $this->_addColumn(trim($row['email']));
                $this->_addColumn($sendnotifications);
                $this->_addColumn($row['invoiceemails']);
                $this->_addColumn($row['supportemails']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportTaxRule()
    {
        $l2compound = '0';
        $vat = '0';

        $query = "SELECT * FROM tblconfiguration WHERE setting IN ('TaxL2Compound', 'TaxVATEnabled')";
        $result = $this->query($query);

        foreach ($result as $row) {
            switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($row['setting'])))) {
                case 'taxl2compound':
                    $l2compound = ($row['value'] == 'on')? '1' : '0';
                    break;
                case 'taxvatenabled':
                    $vat = ($row['value'] == '1')? '1' : '0';
                    break;
            }
        }

        $offset = 0;

        do {
            $query = "SELECT * FROM tbltax LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $countryiso = ($row['country'] != '')? $row['country'] : '_ALL';
                $state = ($row['country'] != '' && $row['state'] != '')? $row['state'] : '_ALL';
                $compound = ($row['level'] == '2' && $l2compound == '1')? '1' : '0';

                $this->_addColumn($countryiso);
                $this->_addColumn($state);
                $this->_addColumn($row['taxrate']);
                $this->_addColumn($vat);
                $this->_addColumn($row['name']);
                $this->_addColumn($row['level']);
                $this->_addColumn($compound);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportKBCategories()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblknowledgebasecats ORDER BY id ASC LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $parentid = ($row['parentid'] != '0')? $row['parentid'] : '-1';
                $staffonly = ($row['hidden'] == 'on')? '1' : '0';
                $catid = ($row['catid'] != '')? $row['catid'] : '0';
                $language = ($row['language'] != '')? ucfirst(strtolower($row['language'])) : '';

                $this->_addColumn($row['id']);
                $this->_addColumn($parentid);
                $this->_addColumn($row['name']);
                $this->_addColumn($row['description']);
                $this->_addColumn($staffonly);
                $this->_addColumn($catid);
                $this->_addColumn($language);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportKBArticles()
    {
        $offset = 0;

        do {
            $query = "SELECT kb.*, IFNULL(kbl.categoryid, '-1') AS categoryid FROM tblknowledgebase kb LEFT JOIN tblknowledgebaselinks kbl ON kb.id = kbl.articleid ORDER BY kb.id ASC LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);
            $numRows = $this->getNumRows();

            foreach ($result as $row) {
                $access = ($row['private'] == 'on')? '0' : '2';
                $categoryid = ($row['categoryid'] != '0')? $row['categoryid'] : '-1';
                $parentid = ($row['parentid'] != '')? $row['parentid'] : '0';
                $language = ($row['language'] != '')? ucfirst(strtolower($row['language'])) : '';
                $tags = '';

                $queryTags = "SELECT * FROM tblknowledgebasetags WHERE articleid = '".$row['id']."'";
                $resultTags = $this->query($queryTags);

                foreach ($resultTags as $rowTags) {
                    $tags .= ($tags !== '')? ',' : '';
                    $tags .= $rowTags['tag'];
                }

                $this->_addColumn($row['id']);
                $this->_addColumn($row['title']);
                $this->_addColumn($row['article']);
                $this->_addColumn($access);
                $this->_addColumn($row['order']);
                $this->_addColumn($categoryid);
                $this->_addColumn($tags);
                $this->_addColumn($parentid);
                $this->_addColumn($language);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($numRows >= 1);
    }

    public function exportKBArticlesFiles()
    {
        //New Table
        $this->query("SHOW TABLES LIKE 'tblknowledgebase_images'");
        $existstblknowledgebase_images = ($this->getNumRows())? true : false;

        if ($existstblknowledgebase_images) {
            $offset = 0;

            do {
                $query = "SELECT * FROM tblknowledgebase_images ORDER BY id ASC LIMIT {$offset}, {$this->_mysqlBufferLimit}";
                $result = $this->query($query);

                foreach ($result as $row) {
                    $this->_addColumn($row['id']);
                    $this->_addColumn($row['original_name']);
                    $this->_addColumn($row['created_at']);
                    $this->_addColumn($row['filename']);
                    $this->_addLine();
                }

                $offset += $this->_mysqlBufferLimit;
            } while ($this->getNumRows() >= 1);
        }
    }

    public function exportCannedResponse()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblticketpredefinedreplies LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['name']);
                $this->_addColumn($row['reply']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportSMTP()
    {
        $hash = $this->encryptionHash;

        $query = "SELECT value FROM tblconfiguration WHERE setting = 'MailConfig' ";
        $result = $this->query($query);

        foreach ($result as $row) {
            $value1 = base64_decode($row['value']);
            $value2 = '';

            $hash = sha1(md5(md5($hash)) . md5($hash));
            $temphash = '';

            for ($i = 0; $i < strlen($hash); $i += 2) {
                $temphash .= chr(hexdec($hash[$i] . $hash[$i + 1]));
            }

            $hash = $temphash;
            $hashlength = strlen($hash);

            $hashseed = substr($value1, 0, $hashlength);
            $value1 = substr($value1, $hashlength, strlen($value1) - $hashlength);

            $value3 = '';

            for ($i = 0; $i < $hashlength; $i++) {
                $value3 .= chr(ord($hashseed[$i]) ^ ord($hash[$i]));
            }

            for ($i = 0; $i < strlen($value1); $i++) {
                if ($i != 0 && $i % $hashlength == 0) {
                    $tempvalue = sha1($value3 . substr($value2, $i - $hashlength, $hashlength));
                    $value3 = '';

                    for ($j = 0; $j < strlen($tempvalue); $j += 2) {
                        $value3 .= chr(hexdec($tempvalue[$j] . $tempvalue[$j+1]));
                    }
                }

                $value2 .= chr(ord($value3[$i % $hashlength]) ^ ord($value1[$i]));
            }

            $valuesArray = json_decode($value2, true);

            if ($valuesArray['module'] == "SmtpMail") {
                $this->_addColumn($valuesArray['configuration']['host']);
                $this->_addColumn($valuesArray['configuration']['username']);
                $this->_addColumn($valuesArray['configuration']['password']);
                $this->_addColumn($valuesArray['configuration']['port']);
                $this->_addLine();
            }
        }
    }

    public function exportEmail()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblemails LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['id']);
                $this->_addColumn($row['userid']);
                $this->_addColumn($row['subject']);
                $this->_addColumn('1');
                $this->_addColumn(Clientexec::encryptString($row['message']));
                $this->_addColumn($row['date']);
                $this->_addColumn($row['to']);
                $this->_addColumn('');
                $this->_addColumn('');
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function exportCreditHistory()
    {
        $offset = 0;

        do {
            $query = "SELECT * FROM tblcredit LIMIT {$offset}, {$this->_mysqlBufferLimit}";
            $result = $this->query($query);

            foreach ($result as $row) {
                $this->_addColumn($row['date']);
                $this->_addColumn($row['clientid']);
                $this->_addColumn($row['admin_id']);
                $this->_addColumn($row['description']);
                $this->_addColumn($row['amount']);
                $this->_addLine();
            }

            $offset += $this->_mysqlBufferLimit;
        } while ($this->getNumRows() >= 1);
    }

    public function ticketUrgency2ce($urgency)
    {
        switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($urgency)))) {
            case 'low':
                $priority = 3;
                break;
            case 'medium':
                $priority = 2;
                break;
            case 'high':
            case 'critical':
                $priority = 1;
                break;
        }
        return $priority;
    }

    public function ticketStatus2ce($status)
    {
        switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($status)))) {
            case 'open':
                $ceStatus = TICKET_STATUS_OPEN;
                break;
            case 'closed':
                $ceStatus = TICKET_STATUS_CLOSED;
                break;
            case 'answered':
                $ceStatus = TICKET_STATUS_WAITINGONCUSTOMER;
                break;
            case 'onhold':
            case 'inprogress':
            case 'customerreply':
            default:
                $ceStatus = TICKET_STATUS_WAITINGONTECH;
                break;
        }
        return $ceStatus;
    }

    public function userStatus2ce ($status)
    {
        switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($status)))) {
            case 'draft':
            case 'pending':
            case 'pendingtransfer':
                $ce_status = USER_STATUS_PENDING;
                break;
            case 'active':
            case 'paid':
                $ce_status = USER_STATUS_ACTIVE;
                break;
            case 'inactive':
            case 'suspended':
                $ce_status = USER_STATUS_INACTIVE;
                break;
            case 'closed':
            case 'cancelled':
            case 'refunded':
            case 'terminated':
            case 'unpaid':
            case 'expired':
                $ce_status = USER_STATUS_CANCELLED;
                break;
            case 'fraud':
                $ce_status = USER_STATUS_FRAUD;
                break;
            default:
                if (empty($status)) {
                    throw new Exception('Unable to convert empty user status.');
                } else {
                    throw new Exception("Unable to convert the user status '{$status}'.");
                }
        }
        return $ce_status;
    }

    public function packageStatus2ce ($status)
    {
        switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($status)))) {
            case 'draft':
            case 'pending':
            case 'paymentpending':
            case 'pendingregistration':
            case 'pendingtransfer':
                $ce_status = PACKAGE_STATUS_PENDING;
                break;
            case 'active':
            case 'paid':
            case 'collections':
            case 'completed':
                $ce_status = PACKAGE_STATUS_ACTIVE;
                break;
            case 'closed':
            case 'suspended':
                $ce_status = PACKAGE_STATUS_SUSPENDED;
                break;
            case 'cancelled':
            case 'fraud':
            case 'inactive':
            case 'refunded':
            case 'terminated':
            case 'transferredaway':
                $ce_status = PACKAGE_STATUS_CANCELLED;
                break;
            case 'unpaid':
                $ce_status = PACKAGE_STATUS_PENDINGCANCELLATION;
                break;
            case 'expired':
            case 'grace':
            case 'redemption':
                $ce_status = PACKAGE_STATUS_EXPIRED;
                break;
            default:
                if (empty($status)) {
                    throw new Exception('Unable to convert empty package status.');
                } else {
                    throw new Exception("Unable to convert the package status '{$status}'.");
                }
        }
        return $ce_status;
    }

    public function invoiceStatus2ce ($status)
    {
        switch (trim(str_replace(array(' ', '-', '_'), '', strtolower($status)))) {
            case 'draft':
                $ce_status = INVOICE_STATUS_DRAFT;
                break;
            case 'pending':
            case 'paymentpending':
            case 'pendingregistration':
            case 'pendingtransfer':
                $ce_status = INVOICE_STATUS_PENDING;
                break;
            case 'active':
            case 'paid':
                $ce_status = INVOICE_STATUS_PAID;
                break;
            case 'refunded':
                $ce_status = INVOICE_STATUS_REFUNDED;
                break;
            case 'unpaid':
            case 'collections':
                $ce_status = INVOICE_STATUS_UNPAID;
                break;
            case 'closed':
            case 'suspended':
            case 'expired':
            case 'cancelled':
            case 'fraud':
            case 'inactive':
            case 'terminated':
            case 'void':
                $ce_status = INVOICE_STATUS_VOID;
                break;
            default:
                if (empty($status)) {
                    throw new Exception('Unable to convert empty invoice status.');
                } else {
                    throw new Exception("Unable to convert the invoice status '{$status}'.");
                }
        }
        return $ce_status;
    }
}

function getData ($db_host, $db_name, $db_username, $db_password, $db_port, $cc_encryption_hash)
{
    @set_time_limit(0);
    $exporter = new WHMCS_Exporter;
    $exporter->encryptionHash = $cc_encryption_hash;
    $exporter->connect($db_host, $db_username, $db_password, $db_name, $db_port);
    $exporter->export();
    $exporter->closeConnection();

    return $exporter->getBuffer();
}