<?php 
/**
 * FBA Estimated Fee Preview
 * 
 * @package     Codeigniter
 * @version     3.1.11
 * @subpackage  Controller
 * @author      MD TARIQUE ANWER| mtarique@outlook.com
 */
defined('BASEPATH') or exit('No direct script access allowed'); 

class Fees extends CI_Controller 
{
    public function __construct()
    {
        parent::__construct(); 

        $this->load->helper(array('auth_helper', 'fba_fees_calc_helper')); 

        $this->load->library(array('mws/reports', 'encryption')); 

        $this->load->model(array('payments/amazon/payments_model', 'settings/channels/amazon_model', 'products/amazon/products_model')); 
    }

    /**
     * View Amazon FBA Fee Preview page
     *
     * @return void
     */
    public function index()
    {
        $page_data['title'] = "Fee Preview";
        $page_data['descr'] = "Estimated Amazon Selling and Fulfillment Fees for your current FBA inventory."; 

        $this->load->view('payments/amazon/fees', $page_data);
    }

    /**
     * Get _DONE_ report 
     * 
     * if latest report is available fetch it else request a new report
     *
     * @return void
     */
    public function get_done_report()
    {   
        // Amazon account id & report type
        $amz_acct_id = $this->input->get('amzacctid');
        $report_type = "_GET_FBA_ESTIMATED_FBA_FEES_TXT_DATA_"; 

        // Get Amazon MWS Access keys by amazon account id
        $result = $this->amazon_model->get_mws_keys($amz_acct_id);

        if(!empty($result))
        {   
            // MWS API Keys
            $seller_id         = $this->encryption->decrypt($result[0]->seller_id); 
            $mws_auth_token    = $this->encryption->decrypt($result[0]->mws_auth_token); 
            $aws_access_key_id = $this->encryption->decrypt($result[0]->aws_access_key_id); 
            $secret_key        = $this->encryption->decrypt($result[0]->secret_key);
            $curr_code         = $result[0]->curr_code; 

            // Get recent and latest _DONE_ report
            $response = $this->reports->GetReportRequestList($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, 1, null, null, null, $report_type, '_DONE_');

            // Get response as XML
            $xml = new SimpleXMLElement($response); 

            // Validate XML response 
            if(isset($xml->GetReportRequestListResult))
            {
                if(isset($xml->GetReportRequestListResult->ReportRequestInfo->ReportProcessingStatus) && $xml->GetReportRequestListResult->ReportRequestInfo->ReportProcessingStatus == "_DONE_")
                {
                    $last_submitted_date = date("Y-m-d", strtotime($xml->GetReportRequestListResult->ReportRequestInfo->SubmittedDate)); 

                    // If done report is older than 2 days
                    if(strtotime($last_submitted_date) < strtotime('-2 day'))
                    {   
                        $json_data = $this->request_report($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $report_type); 
                    }
                    else {
                        // Get Fee Preview report
                        $json_data = $this->get_report($amz_acct_id, $seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $curr_code, $xml->GetReportRequestListResult->ReportRequestInfo->GeneratedReportId); 
                    }
                }
                else {
                    // Request a new report
                    $json_data = $this->request_report($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $report_type); 
                }
            }
            else {
                $json_data['status']  = false; 
                $json_data['message'] = show_alert('danger', $xml->Error->Message);
            }
        }
        else {
            $json_data['status']  = false; 
            $json_data['message'] = show_alert('danger', "Amazon account details not found.");
        }

        echo json_encode($json_data); 
    }

    /**
     * Request new report
     *
     * @param  string   $seller_id
     * @param  string   $mws_auth_token
     * @param  string   $aws_access_key_id
     * @param  string   $secret_key
     * @param  string   $report_type
     * @return array
     */
    public function request_report($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $report_type)
    {   
        // Request report
        $response = $this->reports->RequestReport($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $report_type, null, date('c', strtotime('-72 hours')), date('c'));
        
        // Get response in XML
        $xml = new SimpleXMLElement($response); 

        if(isset($xml->RequestReportResult->ReportRequestInfo->ReportRequestId))
        {
            $json_data['status']     = true; 
            $json_data['message']    = "REPORT_REQUESTED";
            $json_data['rep_req_id'] = $xml->RequestReportResult->ReportRequestInfo->ReportRequestId; 
        }
        else {
            $json_data['status']  = false;
            $json_data['message'] = show_alert('danger', $xml->Error->Message); 
        }

        return $json_data; 
    }

    /**
     * Get report status
     *
     * @return void
     */
    public function get_report_status()
    {
        $amz_acct_id = $this->input->get('amzacctid'); 
        $rep_req_id  = $this->input->get('repreqid'); 
        $report_type = "_GET_FBA_ESTIMATED_FBA_FEES_TXT_DATA_";

        // Get Amazon MWS Access keys by amazon account id
        $result = $this->amazon_model->get_mws_keys($amz_acct_id);

        if(!empty($result)) 
        {   
            // MWS API Keys
            $seller_id         = $this->encryption->decrypt($result[0]->seller_id); 
            $mws_auth_token    = $this->encryption->decrypt($result[0]->mws_auth_token); 
            $aws_access_key_id = $this->encryption->decrypt($result[0]->aws_access_key_id); 
            $secret_key        = $this->encryption->decrypt($result[0]->secret_key); 

            // Get report request list by report id
            $response = $this->reports->GetReportRequestList($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, null, null, null, $rep_req_id);
            
            $xml = new SimpleXMLElement($response); 

            // If valid response
            if(isset($xml->GetReportRequestListResult->ReportRequestInfo))
            {   
                if(isset($xml->GetReportRequestListResult->ReportRequestInfo->ReportProcessingStatus))
                {
                    $report_status = $xml->GetReportRequestListResult->ReportRequestInfo->ReportProcessingStatus; 

                    if($report_status == "_DONE_")
                    {   
                        $json_data['status'] = true; 
                        $json_data['report_status'] = "_DONE_"; 
                    }
                    elseif($report_status == "_SUBMITTED_" || $report_status == "_IN_PROGRESS_")
                    {   
                        $json_data['status'] = true; 
                        $json_data['report_status'] = "_IN_PROGRESS_"; 
                    }
                    else {
                        $json_data['status'] = false;
                        $json_data['message'] = show_alert('danger', "Report cancelled or have no data in it.");
                    }
                }
                else {
                    $json_data['status']  = false;
                    $json_data['message'] = show_alert('danger', "Report request error."); 
                }
            }
            else {
                $json_data['status']  = false;
                $json_data['message'] = show_alert('danger', $xml->Error->Message); 
            }
        }
        else {
            $json_data['status']  = false; 
            $json_data['message'] = show_alert('danger', "Amazon account details not found.");     
        }

        echo json_encode($json_data);
    }

    /**
     * Get report
     *
     * @param  string   $amz_acct_id
     * @param  string   $seller_id
     * @param  string   $mws_auth_token
     * @param  string   $aws_access_key_id
     * @param  string   $secret_key
     * @param  string   $curr_code      Currency code of selected account
     * @param  string   $gen_rep_id
     * @return void
     */
    public function get_report($amz_acct_id, $seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $curr_code, $gen_rep_id)
    {
        // GetReport MWS Reports API
        $response = $this->reports->GetReport($seller_id, $mws_auth_token, $aws_access_key_id, $secret_key, $gen_rep_id);

        if(!empty($response))
        {   
            // UTF8 encode response array
            $response = utf8_encode($response); 

            // Break the response in rows
            $rows = str_getcsv($response, "\n"); 

            $html_table = '
                <table class="table table-bordered table-hover table-sm small border border-grey-300" id="tblFeePrev">
                    <thead>
                        <tr class="bg-light">
                            <th class="align-middle font-weight-bold text-left">Product Name</th>
                            <th class="align-middle font-weight-bold text-center">SKU</th>
                            <th class="align-middle font-weight-bold text-center">ASIN</th>
                            <th class="align-middle font-weight-bold text-center">Currency</th>
                            <th class="align-middle font-weight-bold text-center">FBA Fees Amazon</th>
                            <th class="align-middle font-weight-bold text-center">FBA Fees Calculated</th>
                            <th class="align-middle font-weight-bold text-center">FBA Fees Difference</th>

                            <th class="align-middle font-weight-bold text-left">Size Tier Amazon</th>
                            <th class="align-middle font-weight-bold text-center">LS</th>
                            <th class="align-middle font-weight-bold text-center">MS</th>
                            <th class="align-middle font-weight-bold text-center">SS</th>
                            <th class="align-middle font-weight-bold text-left">Unit of Dimension</th>
                            <th class="align-middle font-weight-bold text-center">WT</th>
                            <th class="align-middle font-weight-bold text-left">Unit of Weight</th>
                            
                            <th class="align-middle font-weight-bold text-left">Size Tier Calculated</th>
                            <th class="align-middle font-weight-bold text-center">LS</th>
                            <th class="align-middle font-weight-bold text-center">MS</th>
                            <th class="align-middle font-weight-bold text-center">SS</th>
                            <th class="align-middle font-weight-bold text-left">Unit of Dimension</th>
                            <th class="align-middle font-weight-bold text-center">WT</th>
                            <th class="align-middle font-weight-bold text-left">Unit of Weight</th>
                        </tr>
                    </thead>
                    <tbody>
            '; 

            // Loop through response rows slicing first heading row
            foreach(array_slice($rows, 1) as $row)
            {   
                // Extract cells data from row
                $data = ($this->get_delimiter($response, 5) == '\t') ? str_getcsv($row, "\t") : str_getcsv($row, ","); 

                // If currency is 
                if($data['17'] == $curr_code)
                {
                    $ProdDimAmz = number_format($data[9], 2).' x '.number_format($data[10], 2).' x '.number_format($data[11], 2);

                    // Get own product dimensions and calculate FBA Fees
                    $result = $this->products_model->get_fba_prod($amz_acct_id, $data[0]); 

                    if(!empty($result)) 
                    {
                        $ls = $result[0]->longest_side; 
                        $ms = $result[0]->median_side; 
                        $ss = $result[0]->shortest_side; 
                        $wt = $result[0]->pkgd_prod_wt/453.59237; // Gram to pound
                        $dt = date('Y-m-d');

                        //$ProdDimOwn = number_format($ls, 2).' x '.number_format($ms, 2).' x '.number_format($ss, 2);
                        $SizeTierCalculated = get_size_tier(get_size_code($ls, $ms, $ss, $wt, $dt), $dt);  
                        $FBAPerUnitFulfillmentFeeCalculated = get_fba_ful_fees($ls, $ms, $ss, $wt, $dt); 
                    }
                    else {
                        $ls = 0.00; 
                        $ms = 0.00; 
                        $ss = 0.00; 
                        $wt = 0.00; 

                        //$ProdDimOwn = number_format($ls, 2).' x '.number_format($ms, 2).' x '.number_format($ss, 2);
                        $SizeTierCalculated = "--"; 
                        $FBAPerUnitFulfillmentFeeCalculated = 0;
                    } 

                    // Highlight excess FBA fees
                    $excess_marker = (($data[24]-$FBAPerUnitFulfillmentFeeCalculated) > 0) ? 'bg-red-200' : 'bg-green-200'; 

                    $html_table .= '
                        <tr>
                            <td class="align-middle text-left col-wd-500">
                                <div class="d-flex jutify-content-between align-items-center">
                                    <div class="col-md-2">
                                        <img src="https://ws-na.amazon-adsystem.com/widgets/q?_encoding=UTF8&MarketPlace=US&ASIN='.$data[2].'&ServiceVersion=20070822&ID=AsinImage&WS=1&Format=_SL250_" alt="Loading..." class="float-left prod-img"/>
                                    </div>
                                    <div class="col-md-10">
                                        '.$data[3].'
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle text-center">'.$data[0].'</td>
                            <td class="align-middle text-center">'.$data[2].'</td>

                            <td class="align-middle text-center">'.$data[17].'</td>
                            <td class="align-middle text-center">'.$data[24].'</td>
                            <td class="align-middle text-center">'.number_format((float)$FBAPerUnitFulfillmentFeeCalculated, '2', '.', '').'</td>
                            <td class="align-middle text-center '.$excess_marker.'">'.number_format((float)($data[24]-$FBAPerUnitFulfillmentFeeCalculated), '2', '.', '').'</td>

                            <td class="align-middle text-left text-nowrap">'.$data[16].'</td>
                            <td class="align-middle text-center">'.$data[9].'</td>
                            <td class="align-middle text-center">'.$data[10].'</td>
                            <td class="align-middle text-center">'.$data[11].'</td>
                            <td class="align-middle text-left">'.$data[13].'</td>
                            <td class="align-middle text-center">'.$data[14].'</td>
                            <td class="align-middle text-left">'.$data[15].'</td>
                            
                            <td class="align-middle text-left text-nowrap">'.$SizeTierCalculated.'</td>
                            <td class="align-middle text-center">'.number_format($ls, 2).'</td>
                            <td class="align-middle text-center">'.number_format($ms, 2).'</td>
                            <td class="align-middle text-center">'.number_format($ss, 2).'</td>
                            <td class="align-middle text-left">inches</td>
                            <td class="align-middle text-center">'.number_format($wt, 2).'</td>
                            <td class="align-middle text-left">pounds</td>
                        </tr>
                    '; 
                }
            }
            
            $html_table .= '</tbody></table>'; 

            //return $html_table; 
            $json_data['status']  = true;
            $json_data['message'] = 'REPORT_GENERATED';
            $json_data['report']  = $html_table;   
            
        }
        else {
            $json_data['status']  = false; 
            $json_data['message'] = show_alert('danger', "FBA Fee Preview report is not available, please try again."); 
        }

        return $json_data; 
    }

    /**
     * Bulk update product weight and dimensions from excel upload
     *
     * @return void
     */
    public function bulk_upd_prod_dim()
    {   
        // Set form validation rules
        $this->form_validation->set_rules('inputAmzAcctId', 'Amazon Account Id', 'required'); 
        if(empty($_FILES['fileProdDimUploader']['name']))
        {
            $this->form_validation->set_rules('fileProdDimUploader', 'Product dimension uploader file', 'required'); 
        }

        // Validate form input
        if($this->form_validation->run() == true)
        {   
            // Amazon account id
            $amz_acct_id = $this->input->post('inputAmzAcctId'); 

            // Get uploaded file extension
            $ext = pathinfo($_FILES['fileProdDimUploader']['name'], PATHINFO_EXTENSION); 

            // Validate xlsx file type
            if($ext == 'xlsx')
            {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

                // Get file path
                $spreadsheet = $reader->load($_FILES['fileProdDimUploader']['tmp_name']); 

                // Get sheet data from active sheet
                $worksheet = $spreadsheet->getSheetByName('Template'); 

                if($worksheet)
                {   
                    // 
                    if($worksheet->getCellByColumnAndRow(1, 1)->getValue() == "SKU" && 
                       $worksheet->getCellByColumnAndRow(2, 1)->getValue() == "ASIN" && 
                       $worksheet->getCellByColumnAndRow(3, 1)->getValue() == "Packaged Product Weight (grams)" && 
                       $worksheet->getCellByColumnAndRow(4, 1)->getVAlue() == "Longest Side (inches)" && 
                       $worksheet->getCellByColumnAndRow(5, 1)->getValue() == "Median Side (inches)" && 
                       $worksheet->getCellByColumnAndRow(6, 1)->getValue() == "Shortest Side (inches)")
                    {   

                        // Get sheet data 
                        $sheet_data = array_slice($worksheet->toArray(), 1); 

                        $is_valid_sheet = false; 

                        foreach($sheet_data as $sheet_row)
                        {   
                            $sku  = $sheet_row[0]; 
                            $asin = $sheet_row[1]; 
                            $wt   = $sheet_row[2]; 
                            $ls   = $sheet_row[3]; 
                            $ms   = $sheet_row[4]; 
                            $ss   = $sheet_row[5];

                            if(!empty($sku) &&
                               !empty($asin) &&
                               !empty($wt) && $wt != 0 &&
                               !empty($ls) && $ls != 0 &&
                               !empty($ms) && $ms != 0 && 
                               !empty($ss) && $ss != 0)
                            {   
                                $is_valid_sheet = true; 
                                continue; 
                            } 
                            else {
                                $is_valid_sheet = false;
                                break; 
                            }
                        }

                        if($is_valid_sheet)
                        {
                            $result = $this->products_model->upd_prod_dim($amz_acct_id, $sheet_data); 

                            if($result == 1)
                            {
                                $json_data['status']  = true; 
                                $json_data['message'] = show_alert('success', 'Products weight and dimensions successfully updated.');
                            }
                            else {
                                $json_data['status']  = false; 
                                $json_data['message'] = show_alert('danger', 'Database error occurred, please try again.');
                            }
                        }
                        else {
                            $json_data['status']  = false; 
                            $json_data['message'] = show_alert('danger', 'Missing or zero values found in the sheet, please upload a valid file.');
                        }
                    }
                    else {
                        $json_data['status']  = false; 
                        $json_data['message'] = show_alert('danger', 'File heading did not match, please upload a valid file.'); 
                    }
                }
                else {
                    $json_data['status']  = false; 
                    $json_data['message'] = show_alert('danger', 'Template sheet missing, please upload a valid file.'); 
                }
            }
            else {
                $json_data['status'] = false; 
                $json_data['message'] = show_alert('danger', 'Incorrect file type, please upload xlsx file.'); 
            }
        }
        else {
            $json_data['status'] = false; 
            $json_data['message'] = show_alert('danger', validation_errors()); 
        }

        // Send json encoded data as ajax response
        echo json_encode($json_data); 
    }

    /**
     * Get delimeter of text or csv file
     *
     * @param  string   $data
     * @param  integer  $checkLines
     * @return void
     */
    public function get_delimiter($data, $checkLines = 2){
        
        $delimiters = array(',', '\t', ';', '|',':');

        $results = array();

        $i = 0;

        while($i <= $checkLines)
        {
            $line = explode("\n", $data); 

            foreach ($delimiters as $delimiter)
            {
                $regExp = '/['.$delimiter.']/';

                $fields = preg_split($regExp, $line[$i]);

                if(count($fields) > 1)
                {
                    if(!empty($results[$delimiter]))
                    {
                        $results[$delimiter]++;
                    } 
                    else {
                        $results[$delimiter] = 1;
                    }   
                }
            }
           $i++;
        }

        $results = array_keys($results, max($results));
        return $results[0];
    }
}

?>