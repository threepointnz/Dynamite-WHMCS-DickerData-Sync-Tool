<?php
// use the MysqliDb class to interact with WHMCS directly through sql queries
class WHMCS
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * getProblematicClients
     * 
     * @description Get all the clients where custom custom fields 7 and 16 are not both set but data is present
     * @return mixed Query result (depends on $this->db implementation)
     */
    public function getProblematicClients()
    {
        $whmcs_problematic_clients_query = "SELECT
            tblclients.id,
            tblclients.companyname,
            cf7.value AS expiry,
            cf16.value AS tenantId
        FROM `tblclients`
        LEFT JOIN `tblcustomfieldsvalues` AS cf7
            ON tblclients.id = cf7.relid AND cf7.fieldid = 7
        LEFT JOIN `tblcustomfieldsvalues` AS cf16
            ON tblclients.id = cf16.relid AND cf16.fieldid = 16
        WHERE tblclients.status = 'Active'
        AND (
            (cf7.value IS NULL OR cf7.value = '') AND (cf16.value IS NOT NULL AND cf16.value != '') OR
            (cf16.value IS NULL OR cf16.value = '') AND (cf7.value IS NOT NULL AND cf7.value != '')
        )
        ORDER BY tblclients.companyname ASC";

        $result = $this->db->all($whmcs_problematic_clients_query);
        // change values to true/false for state
        foreach ($result as &$row) {
            if ($row['expiry'] == '') {
                $row['expiry'] = 0;
            } else {
                $row['expiry'] = 1;
            }
            if ($row['tenantId'] == '') {
                $row['tenantId'] = 0;
            } else {
                $row['tenantId'] = 1;
            }
        }

        return $result;
    }

    /**
     * getO365Clients
     * 
     * @description Get all Active WHMCS clients with Office 365 related products and their quantities
     * @param mixed $organisedSubscriptions
     * @param mixed $match
     */
    public function getO365Clients()
    {
        $whmcs_o365_clients_query = "SELECT 
            c.id AS client_id,
            c.firstname AS firstname,
            c.lastname AS lastname,
            c.companyname,
            cf7.value AS field7_value,
            cf16.value AS field16_value,
            p.id AS product_id,
            p.name AS product_name,
            SUM(h.qty) AS product_qty
        FROM tblclients c
        JOIN tblcustomfieldsvalues cf7 
            ON c.id = cf7.relid 
        AND cf7.fieldid = 7
        JOIN tblcustomfieldsvalues cf16 
            ON c.id = cf16.relid 
        AND cf16.fieldid = 16
        JOIN tblhosting h
            ON h.userid = c.id
        JOIN tblproducts p
            ON p.id = h.packageid
        WHERE c.status = 'Active'
        AND c.id <> 223 -- exclude Dynamite
        AND cf7.value <> ''
        AND cf16.value <> ''
        AND h.domainstatus = 'Active'
        GROUP BY 
            c.id,
            c.companyname,
            cf7.value,
            cf16.value,
            p.id,
            p.name
        ORDER BY 
            c.companyname ASC,
            p.name ASC;";

        $rows = $this->db->all($whmcs_o365_clients_query);

        foreach ($rows as &$row) {
            // convert tenantID string to array
            if ($row['field16_value'] !== '' && $row['field16_value'] !== null) {
                $row['field16_value'] = explode(',', str_replace(' ', '', $row['field16_value']));
            }
        }

        return $rows;
    }

    /**
     * getDistinctPackages
     * 
     * @description Get a list of distinct product packages from WHMCS
     * @return array
     */
    public function getDistinctPackages()
    {
        $whmcs_distinct_packages_query = "SELECT p.id, p.name
        FROM tblproducts p
        WHERE p.name LIKE '%365%'
        ORDER BY p.id ASC;";

        return $this->db->all($whmcs_distinct_packages_query);
    }

}