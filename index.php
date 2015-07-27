<?php 

    include_once 'config.php';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => "https://api.workflowmax.com/client.api/list?apiKey=" . WORKFLOWMAX_API_KEY . "&accountKey=" . WORKFLOWMAX_ACCOUNT_KEY,
        CURLOPT_USERAGENT => 'Mailchimp Connector'
    ));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($curl);
    curl_close($curl);
    
    if(!$resp){
        die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
    }
    
    $workflowMaxResponse=json_decode(json_encode(simplexml_load_string($resp)),true);
    
    if($workflowMaxResponse['Status'] != 'OK'){
        die('Error: "' . $workflowMaxResponse['Error'] );
    }
    
    $workflowMaxClients = array();
    foreach ($workflowMaxResponse['Clients']['Client'] as $client) {
        $email = (array_key_exists('Email', $client) ? $client['Email']: '');
        array_push($workflowMaxClients, array('name' => $client['Name'], 'email' => $email));
    }
    
    $mailchimp = new Drewm\MailChimp(MAILCHIMP_API_KEY);
    $mailchimpLists = $mailchimp->call('lists/list')['data'];
    $lists = array();
    foreach ($mailchimpLists as $list) {   
        $emails = array();
        foreach ($mailchimp->call('lists/members', array('id' => $list['id']))['data'] as $subscriber) {
            array_push($emails, $subscriber['email']);
        }
        
        array_push($lists, array('name' => $list['name'], 'id' => $list['id'], 'emails' => $emails));
    }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>WorkflowMax to Mailchimp</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <div class="container">
        <h1>WorkflowMax to Mailchimp</h1>
    
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <h2>WorkflowMax Clients</h2>
                <table class="table table-bordered" id="clientTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>In Selected Mailchimp List?</th>
                            <th>
                                <select class="form-control" id="mailchimpList">
                                    <?php foreach($lists as $list){ ?>
                                    <option value="<?php echo $list['id']; ?>"><?php echo $list['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflowMaxClients as $id=>$details) { ?>
                        <tr id="<?php echo $id; ?>">
                            <td id="name_field_<?php echo $id; ?>"><?php echo $details['name']; ?></td>
                            <td id="email_field_<?php echo $id; ?>"><?php echo $details['email']; ?></td>
                            <td id="in_mc_field_<?php echo $id; ?>"></td>
                            <td><button class="btn btn-sm btn-primary" onclick="transfer(<?php echo $id; ?>)">Transfer</button></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script type="text/javascript">
        var mailchimpLists = <?php echo json_encode($lists); ?>;
        
        $(document).ready(function(){
            refreshInMailchimpListColumn();            
        });
        
        $('#mailchimpList').change(function(){
            refreshInMailchimpListColumn();
        });
        
        function refreshInMailchimpListColumn(){
            var selectedList = $('#mailchimpList').val();
            var emails = [];
            for(var i = 0; i < mailchimpLists.length; i++){
                if(mailchimpLists[i].id == selectedList){
                    emails = mailchimpLists[i].emails;
                }
            }
            
            for(var i = 0; i < emails.length; i++){
                emails[i] = emails[i].toLowerCase();
            }
            
            console.info(emails);
            
            $('#clientTable > tbody  > tr').each(function() {
                var clientId = $(this).attr('id');
                var email = $('#email_field_' + clientId).text().toLowerCase();
                var html = '';
                console.info(email);
                if($.inArray(email, emails) !== -1){
                    html = 'Yes';
                }else{
                    html = 'No';
                }
                
                $('#in_mc_field_' + clientId).html(html);
            });
            
        }
        
        function transfer(id){
            var name = $('#name_field_' + id).text();
            var email = $('#email_field_' + id).text();
            var list_id = $('#mailchimpList').val();
            
            $.ajax({
                url: 'transfer.php',
                type: 'POST',
                data: {name: name, email: email, list_id: list_id},
                dataType: 'json',
                success: function(data){
                    if(data.success == 1){
                       for(var i = 0; i < mailchimpLists.length; i++){
                            if(mailchimpLists[i].id == list_id){
                                 mailchimpLists[i].emails.push(email.toLowerCase());
                            }
                        } 
                        refreshInMailchimpListColumn();
                    }else{
                        alert('Error occurred: ' + data.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log(textStatus, errorThrown);
                    alert('Error occurred: ' + textStatus);
                }
            });
            
        }
        
    </script>
  </body>
</html>