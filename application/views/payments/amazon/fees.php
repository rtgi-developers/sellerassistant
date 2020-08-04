<?php 
$this->load->view('templates/header'); 
$this->load->view('templates/topnav'); 
$this->load->view('templates/wrapper'); 
$this->load->view('templates/titlebar'); 
$this->load->view('templates/loader'); 
?>

<style>
    .prod-img{
	   width: 100%!important;
	   min-height: 100px !important;
	   max-height: 100px !important;
	   object-fit: contain;
	}
</style>

<div class="card bg-light border-0 rounded-0 mb-3">
    <div class="card-body">
        <h5 class="card-title">Amazon Account</h5>
        <div class="form-inline">
            <label class="mr-2 sr-only" for="txtAmzAcctId">Amazon Account</label>
            <select id="txtAmzAcctId" class="custom-select custom-select-sm rounded-0 w-25 mr-2">
                <?php echo _options_amz_accts($this->session->userdata('_userid')); ?>
            </select>
            <button type="button" name="btnPrevFees" id="btnPrevFees"class="btn btn-sm btn-primary rounded-0 shadowsm">Preview Fees</button>
        </div>
    </div>
</div>

<div id="resPrevFees"></div>

<?php $this->load->view('templates/footer'); ?>

<script>
    $(document).ready(function(){
        /**
         * Preview fees on button click
         */
        $('#btnPrevFees').click(function(){

            // Check for non empty amazon account id
            if($('#txtAmzAcctId').val() != "")
            {
                const amz_acct_id = $('#txtAmzAcctId').val(); 
                
                // Get fee preview report
                get_done_report(amz_acct_id); 
            }
            else swal({title: "Oops!", text: "Please select an account.", icon: "error"});
            
        }); 

        /*
         * Ajax request to get done report
         * 
         * If _DONE_ reports are available then fetch it 
         * else request a new report check status till it gets _DONE_ 
         *
         * @return mixed 
         */
        function get_done_report(amz_acct_id)
        {
            $.ajax({
                type: "get", 
                url: "<?php echo base_url('payments/amazon/fees/get_done_report');  ?>", 
                data: "amzacctid="+amz_acct_id, 
                dataType: "json", 
                beforeSend: function()
                {
                    $('#loader').removeClass("d-none");
                }, 
                success: function(res)
                {   
                    if(res.status)
                    {   
                        if(res.message == "REPORT_GENERATED")
                        {   
                            // Output report
                            $('#resPrevFees').html(res.report); 

                            // Hide loading animation
                            $('#loader').addClass("d-none");
                        }
                        else {
                            // Wait 10 seconds and check report request status
                            setTimeout(function(){
                                get_report_status(amz_acct_id, res.rep_req_id[0]); 
                            }, 10000); 
                        }
                    }
                    else {
                        // Show error message
                        $('#resPrevFees').html(res.message);

                        // Hide loading animation
                        $('#loader').addClass("d-none");
                    }
                }, 
                error: function(xhr)
                {
                    const xhr_text = xhr.status+" "+xhr.statusText;
                    swal({title: "Request error!", text: xhr_text, icon: "error"});
                }
            });
        }

        /*
         * Ajax request to get report status
         * 
         * @return mixed 
         */
        function get_report_status(amz_acct_id, rep_req_id)
        {
            $.ajax({
                type: "get", 
                url: "<?php echo base_url('payments/amazon/fees/get_report_status'); ?>", 
                data: "amzacctid="+amz_acct_id+"&repreqid="+rep_req_id, 
                dataType: "json", 
                success: function(res)
                {
                    if(res.status)
                    {
                        if(res.report_status == "_DONE_")
                        {
                            get_report(form_data); 
                        }
                        else {
                            // Wait for 5 more seconds and check report status again
                            setTimeout(function(){
                                get_report_status(amz_acct_id, rep_req_id); 
                            }, 5000)
                        }
                    }
                    else {
                        $('#resPrevFees').html(res.message); 
                    }
                }, 
                error: function(xhr)
                {
                    const xhr_text = xhr.status+" "+xhr.statusText;
                    swal({title: "Request error!", text: xhr_text, icon: "error"});
                }
            });
        }
    }); 
</script>