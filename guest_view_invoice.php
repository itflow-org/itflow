<?php include("guest_header.php"); ?>

<?php 

if(isset($_GET['invoice_id'], $_GET['url_key'])){

  $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);
  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices 
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN locations ON primary_location = location_id
    LEFT JOIN contacts ON primary_contact = contact_id
    LEFT JOIN companies ON invoices.company_id = companies.company_id
    LEFT JOIN settings ON settings.company_id = companies.company_id
    WHERE invoice_id = $invoice_id
    AND invoice_url_key = '$url_key'"
  );

  if(mysqli_num_rows($sql) == 1){

    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_prefix = htmlentities($row['invoice_prefix']);
    $invoice_number = htmlentities($row['invoice_number']);
    $invoice_status = htmlentities($row['invoice_status']);
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = htmlentities($row['invoice_currency_code']);
    $invoice_note = htmlentities($row['invoice_note']);
    $invoice_category_id = $row['invoice_category_id'];
    $client_id = $row['client_id'];
    $client_name = htmlentities($row['client_name']);
    $location_address = htmlentities($row['location_address']);
    $location_city = htmlentities($row['location_city']);
    $location_state = htmlentities($row['location_state']);
    $location_zip = htmlentities($row['location_zip']);
    $contact_email = htmlentities($row['contact_email']);
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = htmlentities($row['contact_extension']);
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $client_website = htmlentities($row['client_website']);
    $client_currency_code = htmlentities($row['client_currency_code']);
    $client_net_terms = htmlentities($row['client_net_terms']);
    if($client_net_terms == 0){
      $client_net_terms = $config_default_net_terms;
    }
    $company_id = $row['company_id'];
    $company_name = htmlentities($row['company_name']);
    $company_address = htmlentities($row['company_address']);
    $company_city = htmlentities($row['company_city']);
    $company_state = htmlentities($row['company_state']);
    $company_zip = htmlentities($row['company_zip']);
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = htmlentities($row['company_email']);
    $company_logo = htmlentities($row['company_logo']);
    if(!empty($company_logo)){
      $company_logo_base64 = base64_encode(file_get_contents("uploads/settings/$company_id/$company_logo"));
    }
    $company_locale = htmlentities($row['company_locale']);
    $config_invoice_footer = htmlentities($row['config_invoice_footer']);
    $config_stripe_enable = $row['config_stripe_enable'];
    $config_stripe_publishable = $row['config_stripe_publishable'];
    $config_stripe_secret = $row['config_stripe_secret'];

    //Set Currency Format
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    $ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
    
    $session_user_agent = strip_tags(mysqli_real_escape_string($mysqli,$_SERVER['HTTP_USER_AGENT']));
    $os = strip_tags(mysqli_real_escape_string($mysqli,get_os($session_user_agent)));
    $browser = strip_tags(mysqli_real_escape_string($mysqli,get_web_browser($session_user_agent)));

    //Set Badge color based off of invoice status
    if($invoice_status == "Sent"){
      $invoice_badge_color = "warning text-white";
    }elseif($invoice_status == "Viewed"){
      $invoice_badge_color = "info";
    }elseif($invoice_status == "Partial"){
      $invoice_badge_color = "primary";
    }elseif($invoice_status == "Paid"){
      $invoice_badge_color = "success";
    }elseif($invoice_status == "Cancelled"){
      $invoice_badge_color = "danger";
    }else{
      $invoice_badge_color = "secondary";
    }

    //Update status to Viewed only if invoice_status = "Sent" 
    if($invoice_status == 'Sent'){
      mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Viewed' WHERE invoice_id = $invoice_id");
    }

    //Mark viewed in history
    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Invoice viewed - $ip - $os - $browser', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $company_id");

    //Prevent SQL Error if client_name has ' in their name example Bill's Market
    $client_name_escaped = mysqli_escape_string($mysqli,$row['client_name']);
    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Invoice Viewed', notification = 'Invoice $invoice_prefix$invoice_number has been viewed by $client_name_escaped - $ip - $os - $browser', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = $row['amount_paid'];

    $balance = $invoice_amount - $amount_paid;

    //check to see if overdue
    if($invoice_status !== "Paid" && $invoice_status !== "Draft" && $invoice_status !== "Cancelled"){
      $unixtime_invoice_due = strtotime($invoice_due) + 86400;
      if($unixtime_invoice_due < time()){
        $invoice_color = "text-danger";
      }
    }

  ?>

  <div class="card">
    <div class="card-header bg-light d-print-none">
      <div class="float-right">
        <a class="btn btn-secondary" data-toggle="collapse" href="#collapsePreviousInvoices"><i class="fa fa-fw fa-history"></i> Invoice History</a>
        <a class="btn btn-primary" href="#" onclick="window.print();"><i class="fa fa-fw fa-print"></i> Print</a>
        <a class="btn btn-primary" href="#" onclick="pdfMake.createPdf(docDefinition).download('<?php echo "$invoice_date-$company_name-Invoice-$invoice_prefix$invoice_number.pdf"; ?>');"><i class="fa fa-fw fa-download"></i> Download</a>
        <?php
        if($invoice_status != "Paid" && $invoice_status  != "Cancelled" && $invoice_status != "Draft" && $config_stripe_enable == 1){
        ?>
        <?php
        if($config_stripe_enable == 1){
        ?>
        <a class="btn btn-success" href="guest_pay.php?invoice_id=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-credit-card"></i> Pay Online <small>(Coming Soon)</small></a>
        <?php } ?>
        <?php } ?>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-4">
        <div class="col-sm-2">
          <img class="img-fluid" src="<?php echo "uploads/settings/$company_id/$company_logo"; ?>">
        </div>
        <div class="col-sm-10">
          <?php if($invoice_status == "Paid"){ ?>
          <div class="ribbon-wrapper">
            <div class="ribbon bg-success">
              <?php echo $invoice_status; ?>
            </div>
          </div>
          <?php } ?>
          <h3 class="text-right mt-5"><strong>Invoice</strong><br><small class="text-secondary"><?php echo "$invoice_prefix$invoice_number"; ?></small></h3>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm">    
          <ul class="list-unstyled">
            <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
            <li><?php echo $company_address; ?></li>
            <li><?php echo "$company_city $company_state $company_zip"; ?></li>
            <li><?php echo $company_phone; ?></li>
            <li><?php echo $company_email; ?></li>
          </ul>
          
        </div>
        <div class="col-sm">

          <ul class="list-unstyled text-right">
            <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
            <li><?php echo $location_address; ?></li>
            <li><?php echo "$location_city $location_state $location_zip"; ?></li>
            <li><?php echo "$contact_phone $contact_extension"; ?></li>
            <li><?php echo $contact_mobile; ?></li>
            <li><?php echo $contact_email; ?></li>
          </ul>
        
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm-8">
        </div>
        <div class="col-sm-4">
          <table class="table">
            <tr>
              <td>Date</td>
              <td class="text-right"><?php echo $invoice_date; ?></td>
            </tr>
            <tr>
              <td>Due</td>
              <td class="text-right"><div class="<?php echo $invoice_color; ?>"><?php echo $invoice_due; ?></div></td>
            </tr>
          </table>
        </div>
      </div>

      <?php $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC"); ?>

      <div class="row mb-4">
        <div class="col-md-12">
          <div class="card">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  
                  $total_tax = 0;
                  $sub_total = 0;
            
                  while($row = mysqli_fetch_array($sql_invoice_items)){
                    $item_id = $row['item_id'];
                    $item_name = htmlentities($row['item_name']);
                    $item_description = htmlentities($row['item_description']);
                    $item_quantity = floatval($row['item_quantity']);
                    $item_price = floatval($row['item_price']);
                    $item_tax = floatval($row['item_tax']);
                    $item_total = floatval($row['item_total']);
                    $total_tax = $item_tax + $total_tax;
                    $sub_total = $item_price * $item_quantity + $sub_total;

                  ?>

                  <tr>
                    <td><?php echo $item_name; ?></td>
                    <td><div style="white-space:pre-line"><?php echo $item_description; ?></div></td>
                    <td class="text-center"><?php echo $item_quantity; ?></td>
                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $invoice_currency_code); ?></td>
                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $invoice_currency_code); ?></td>
                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $invoice_currency_code); ?></td>  
                  </tr>

                  <?php 

                  }

                  ?>

                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-sm-7">
          <?php if(!empty($invoice_note)){ ?>
          <div class="card">
            <div class="card-body">
              <div style="white-space:pre-line"><?php echo $invoice_note; ?></div>
            </div>
          </div>
          <?php } ?>
        </div>
        <div class="col-sm-3 offset-sm-2">
          <table class="table table-borderless">
            <tbody>    
              <tr class="border-bottom">
                <td>Subtotal</td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code); ?></td>
              </tr>
              <?php if($total_tax > 0){ ?>
              <tr class="border-bottom">
                <td>Tax</td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code); ?></td>        
              </tr>
              <?php } ?>
              <?php if($amount_paid > 0){ ?>
              <tr class="border-bottom">
                <td><div class="text-success">Paid</div></td>
                <td class="text-right text-success"><?php echo numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code); ?></td>
              </tr>
              <?php } ?>
              <tr class="border-bottom">
                <td><strong>Balance</strong></td>
                <td class="text-right"><strong><?php echo numfmt_format_currency($currency_format, $balance, $invoice_currency_code); ?></strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <hr class="mt-5">

      <center style="white-space:pre-line"><?php echo $config_invoice_footer; ?></center>
    </div>
  </div>
  
  <script src='plugins/pdfmake/pdfmake.min.js'></script>
<script src='plugins/pdfmake/vfs_fonts.js'></script>
<script>

var docDefinition = {
	info: {
		title: <?php echo json_encode(html_entity_decode($company_name) . "- Invoice") ?>,
		author: <?php echo json_encode(html_entity_decode($company_name)) ?>
	},

	//watermark: {text: '<?php echo $invoice_status; ?>', color: 'lightgrey', opacity: 0.3, bold: true, italics: false},

	content: [
		// Header
		{
			columns: [
				<?php if(!empty($company_logo_base64)){ ?>
				{
					image: <?php echo json_encode("data:image;base64,$company_logo_base64") ?>,
					width: 120
				},
				<?php } ?>
		            
				[
					{
						text: 'Invoice', 
						style: 'invoiceTitle',
						width: '*'
					},
					{
						text: <?php echo json_encode(html_entity_decode("$invoice_prefix$invoice_number")) ?>, 
						style: 'invoiceNumber',
						width: '*'
					},
		    ],
	 		],
		},
		// Billing Headers
		{
			columns: [
				{
		    	text: <?php echo json_encode(html_entity_decode($company_name)) ?>,
		      style: 'invoiceBillingTitle',
		    },
		    {
		      text: <?php echo json_encode(html_entity_decode($client_name)) ?>,
		      style: 'invoiceBillingTitleClient',        
		    },
			]
		},
		// Billing Address
		{
			columns: [
		  	{
		      text: <?php echo json_encode(html_entity_decode("$company_address \n $company_city $company_state $company_zip \n $company_phone \n $company_website")) ?>,
		      style: 'invoiceBillingAddress'
		    },
		    {
		      text: <?php echo json_encode(html_entity_decode("$location_address \n $location_city $location_state $location_zip \n $contact_email \n $contact_phone")) ?>,
		      style: 'invoiceBillingAddressClient'
		    },
		  ]
		},
		//Invoice Dates Table
		{
			table: {
		  	// headers are automatically repeated if the table spans over multiple pages
		    // you can declare how many rows should be treated as headers
		    headerRows: 0,
		    widths: [ '*',80, 80 ],

		    body: [
		    	// Total
		      [ 
		      	{
		      		text: '',
		      		rowSpan: 3
		      	},
		      	{}, 
						{},
		      ],
		      [ 
		      	{},
		      	{
							text: 'Date',
							style: 'invoiceDateTitle'
						}, 
						{
							text: <?php echo json_encode($invoice_date) ?>,
							style: 'invoiceDateValue'
						},
		      ],
		      [ 
		      	{},
		      	{
							text: 'Due',
							style: 'invoiceDateTitle'
						}, 
						{
							text: <?php echo json_encode($invoice_due) ?>,
							style: 'invoiceDateValue'
						},
		      ],
		    ]
		  }, // table
		  layout: 'lightHorizontalLines'
		},
		// Line breaks
		'\n\n',
		// Items
		{
			table: {
				// headers are automatically repeated if the table spans over multiple pages
				// you can declare how many rows should be treated as headers
				headerRows: 1,
				widths: [ '*', 40, 'auto', 'auto', 80 ],

				body: [
					// Table Header
					[ 
						{
							text: 'Product',
							style: [ 'itemsHeader', 'left']
						}, 
						{
						  text: 'Qty',
						  style: [ 'itemsHeader', 'center']
						}, 
						{
						  text: 'Price',
						  style: [ 'itemsHeader', 'right']
						}, 
						{
						  text: 'Tax',
						  style: [ 'itemsHeader', 'right']
						}, 
						{
						  text: 'Total',
						  style: [ 'itemsHeader', 'right']
						} 
					],
		      // Items
		      <?php 
		      $total_tax = 0;
		      $sub_total = 0;

		      $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC");
		      
		      while($row = mysqli_fetch_array($sql_invoice_items)){
		        $item_name = $row['item_name'];
		        $item_description = $row['item_description'];
		        $item_quantity = $row['item_quantity'];
		        $item_price = $row['item_price'];
		        $item_subtotal = $row['item_price'];
		        $item_tax = $row['item_tax'];
		        $item_total = $row['item_total'];
		        $tax_id = $row['item_tax_id'];
		        $total_tax = $item_tax + $total_tax;
		        $sub_total = $item_price * $item_quantity + $sub_total;
		      ?>

		      // Item
		      [	 
		        [
		        	{
		          	text: <?php echo json_encode($item_name) ?>,
		            style:'itemTitle'
		          },
		          {
		            text: <?php echo json_encode($item_description) ?>,
		            style:'itemDescription'       
		          }
		        ], 
		        {
		        	text: <?php echo json_encode($item_quantity) ?>,
		          style: 'itemQty'
		        }, 
		        {
		        	text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_price, $invoice_currency_code)) ?>,
		         	style: 'itemNumber'
		        }, 
		        {
		          text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_tax, $invoice_currency_code)) ?>,
		          style: 'itemNumber'
		        }, 
		        {
		          text:  <?php echo json_encode(numfmt_format_currency($currency_format, $item_total, $invoice_currency_code)) ?>,
		          style: 'itemNumber'
		        } 
		    	],
		
					<?php
					}
					?>
		      // END Items
		    ]
		  }, // table
		  layout: 'lightHorizontalLines'
	 	},
	 	// TOTAL
		{
			table: {
		  	// headers are automatically repeated if the table spans over multiple pages
		    // you can declare how many rows should be treated as headers
		    headerRows: 0,
		    widths: [ '*','auto', 80 ],

		    body: [
		    	// Total
		      [ 
		      	{
		      		text: 'Notes',
		  				style: 'notesTitle'
		  			},
		      	{},
		      	{}
		      ],
		      [ 
		      	{
		      		rowSpan: 5,
							text: <?php echo json_encode(html_entity_decode($invoice_note)) ?>,
		  				style: 'notesText'
		  			},
		      	{
		        	text: 'Subtotal',
		          style: 'itemsFooterSubTitle'
		        }, 
		        { 
		         	text: <?php echo json_encode(numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code)) ?>,
		          style: 'itemsFooterSubValue'
		        }
		      ],
		      [ 
		      	{},    	
		      	{
		        	text: 'Tax',
		          style: 'itemsFooterSubTitle'
		        },
		        {
		         	text: <?php echo json_encode(numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code)) ?>,
		          style: 'itemsFooterSubValue'
		        }
		      ],
		      [ 
		      	{},
		      	{
		        	text: 'Total',
		          style: 'itemsFooterSubTitle'
		        }, 
		        {
		         	text: <?php echo json_encode(numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code)) ?>,
		          style: 'itemsFooterSubValue'
		        }
		      ],
		      [ 
		      	{},
		      	{
		        	text: 'Paid',
		          style: 'itemsFooterSubTitle'
		        },
		        {
		          text: <?php echo json_encode(numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code)) ?>,
		          style: 'itemsFooterSubValue'
		        }
		      ],
		      [ 
		      	{},
		      	{
		        	text: 'Balance',
		          style: 'itemsFooterTotalTitle'
		        },
		        {
		        	text: <?php echo json_encode(numfmt_format_currency($currency_format, $balance, $invoice_currency_code)) ?>,
		          
		          style: 'itemsFooterTotalTitle'
		        }
		      ],
		    ]
		  }, // table
		  layout: 'lightHorizontalLines'
		},
    // TERMS / FOOTER
    {
      text: <?php echo json_encode(html_entity_decode($config_invoice_footer)) ?>,
      style: 'documentFooterCenter'  
    }
	], //End Content,
	styles: {
		// Document Footer
		documentFooterCenter: {
			fontSize: 9,
		  margin: [10,50,10,10],
		  alignment: 'center'
		},
		// Invoice Title
		invoiceTitle: {
			fontSize: 18,
			bold: true,
			alignment: 'right',
			margin: [0,0,0,3]
		},
		// Invoice Number
		invoiceNumber: {
			fontSize: 14,
			alignment: 'right'
		},
		// Billing Headers
		invoiceBillingTitle: {
			fontSize: 14,
			bold: true,
			alignment: 'left',
			margin: [0,20,0,5]
		},
		invoiceBillingTitleClient: {
			fontSize: 14,
			bold: true,
			alignment: 'right',
			margin: [0,20,0,5]
		},
		// Billing Details
		invoiceBillingAddress: {
		  fontSize: 10,
		  lineHeight: 1.2
		},
		invoiceBillingAddressClient: {
			fontSize: 10,
			lineHeight: 1.2,
			alignment: 'right',
			margin: [0,0,0,30]
		},
		// Invoice Dates
		invoiceDateTitle: {
			fontSize: 10,
			alignment: 'left',
			margin: [0,5,0,5]
		},
		invoiceDateValue: {
			fontSize: 10,
			alignment: 'right',
			margin: [0,5,0,5]
		},
		// Items Header
		itemsHeader: {
			fontSize: 10,
			margin: [0,5,0,5],
			bold: true,
			alignment: 'right'
		},
		// Item Title
		itemTitle: {
			fontSize: 10,
			bold: true,
			margin: [0,5,0,3]
		},
		itemDescription: {
			italics: true,
			fontSize: 9,
			lineHeight: 1.1,
			margin: [0,3,0,5]
		},
		itemQty: {
			fontSize: 10,
			margin: [0,5,0,5],
			alignment: 'center'
		},
		itemNumber: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  alignment: 'right'
		},
		itemTotal: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  bold: true,
		  alignment: 'right'
		},
		// Items Footer (Subtotal, Total, Tax, etc)
		itemsFooterSubTitle: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  alignment: 'right'
		},
		itemsFooterSubValue: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  bold: false,
		  alignment: 'right'
		},
		itemsFooterTotalTitle: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  bold: true,
		  alignment: 'right'
		},
		itemsFooterTotalValue: {
		  fontSize: 10,
		  margin: [0,5,0,5],
		  bold: true,
		  alignment: 'right'
		},
		notesTitle: {
			fontSize: 10,
			bold: true,  
			margin: [0,5,0,5]
		},
		notesText: {
			fontSize: 9,
			margin: [0,5,50,5]
		},
		left: {
			alignment: 'left'
		},
		center: {
			alignment: 'center'
		},
	},
	defaultStyle: {
		columnGap: 20
	}
}
</script>

  <?php

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_client_id = $client_id AND invoice_due < CURDATE() AND(invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial') ORDER BY invoice_date DESC");

  if(mysqli_num_rows($sql) > 1){

  ?>


    <div class="card d-print-none card-danger">
      <div class="card-header">
        <strong><i class="fa fa-fw fa-exclamation-triangle"></i> Previous Unpaid Invoices</strong>
      </div>
      <div card="card-body">
        <table class="table">
          <thead>
            <tr>
              <th class="text-center">Invoice #</th>
              <th>Date</th>
              <th>Due Date</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql)){
              $invoice_id = $row['invoice_id'];
              $invoice_prefix = htmlentities($row['invoice_prefix']);
              $invoice_number = htmlentities($row['invoice_number']);
              $invoice_date = $row['invoice_date'];
              $invoice_due = $row['invoice_due'];
              $invoice_amount = floatval($row['invoice_amount']);
              $invoice_currency_code = htmlentities($row['invoice_currency_code']);
              $invoice_url_key = htmlentities($row['invoice_url_key']);
              $invoice_tally_total = $invoice_amount + $invoice_tally_total;
              $difference = time() - strtotime($invoice_due);
              $days = floor($difference / (60*60*24) );

            ?>

              <tr <?php if($_GET['invoice_id'] == $invoice_id){ echo "class='table-active'"; } ?>>
                <th class="text-center"><a href="guest_view_invoice.php?invoice_id=<?php echo $invoice_id; ?>&url_key=<?php echo $invoice_url_key; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></th>
                <td><?php echo $invoice_date; ?></td>
                <td class="text-danger text-bold"><?php echo $invoice_due; ?> (<?php echo $days; ?> Days Late)</td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
              </tr>

            <?php 
            } 
            ?>

          </tbody>
        </table>
      </div>
    </div>
  <?php
  }
  ?>

  <?php

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_client_id = $client_id AND invoice_due > CURDATE() AND(invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial') ORDER BY invoice_number DESC");

  if(mysqli_num_rows($sql) > 1){

  ?>


    <div class="card d-print-none card-light">
      <div class="card-header">
        <strong><i class="fa fa-fw fa-clock"></i> Current Invoices</strong>
      </div>
      <div card="card-body">
        <table class="table">
          <thead>
            <tr>
              <th class="text-center">Invoice #</th>
              <th>Date</th>
              <th>Due</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql)){
              $invoice_id = $row['invoice_id'];
              $invoice_prefix = htmlentities($row['invoice_prefix']);
              $invoice_number = htmlentities($row['invoice_number']);
              $invoice_date = $row['invoice_date'];
              $invoice_due = $row['invoice_due'];
              $invoice_amount = floatval($row['invoice_amount']);
              $invoice_currency_code = htmlentities($row['invoice_currency_code']);
              $invoice_url_key = htmlentities($row['invoice_url_key']);
              $invoice_tally_total = $invoice_amount + $invoice_tally_total;
              $difference = strtotime($invoice_due) - time();
              $days = floor($difference / (60*60*24) );

            ?>

              <tr <?php if($_GET['invoice_id'] == $invoice_id){ echo "class='table-active'"; } ?>>
                <th class="text-center"><a href="guest_view_invoice.php?invoice_id=<?php echo $invoice_id; ?>&url_key=<?php echo $invoice_url_key; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></th>
                <td><?php echo $invoice_date; ?></td>
                <td><?php echo $invoice_due; ?> (Due in <?php echo $days; ?> Days)</td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
              </tr>

            <?php 
            } 
            ?>

          </tbody>
        </table>
      </div>
    </div>
  <?php
  }
  ?>


  <?php

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_client_id = $client_id AND invoice_status = 'Paid' ORDER BY invoice_date DESC");

  if(mysqli_num_rows($sql) > 1){

  ?>


    <div class="card d-print-none collapse" id="collapsePreviousInvoices">
      <div class="card-header bg-dark">
        <strong><i class="fa fa-fw fa-history"></i> Previous Invoices Paid</strong>
      </div>
      <div card="card-body">
        <table class="table">
          <thead>
            <tr>
              <th class="text-center">Invoice #</th>
              <th>Date</th>
              <th>Due Date</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql)){
              $invoice_id = $row['invoice_id'];
              $invoice_prefix = htmlentities($row['invoice_prefix']);
              $invoice_number = htmlentities($row['invoice_number']);
              $invoice_date = $row['invoice_date'];
              $invoice_due = $row['invoice_due'];
              $invoice_amount = floatval($row['invoice_amount']);
              $invoice_currency_code = htmlentities($row['invoice_currency_code']);
              $invoice_url_key = htmlentities($row['invoice_url_key']);
              $invoice_tally_total = $invoice_amount + $invoice_tally_total;

            ?>

              <tr <?php if($_GET['invoice_id'] == $invoice_id){ echo "class='table-active'"; } ?>>
                <th class="text-center"><a href="guest_view_invoice.php?invoice_id=<?php echo $invoice_id; ?>&url_key=<?php echo $invoice_url_key; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></th>
                <td><?php echo $invoice_date; ?></td>
                <td><?php echo $invoice_due; ?></td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
              </tr>

              <tr>
                <th colspan="4">Payments</th>
              </tr>
              
              <?php
              
              $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_invoice_id = $invoice_id ORDER BY payment_date DESC");

              while($row = mysqli_fetch_array($sql_payments)){
                $payment_id = $row['payment_id'];
                $payment_date = $row['payment_date'];
                $payment_amount = floatval($row['payment_amount']);
                $payment_currency_code = htmlentities($row['payment_currency_code']);
                $payment_method = htmlentities($row['payment_method']);
                $payment_reference = htmlentities($row['payment_reference']);
                if(strtotime($payment_date) > strtotime($invoice_due)){
                  $payment_note = "Late";
                  $difference = strtotime($payment_date) - strtotime($invoice_due);
                  $days = floor($difference / (60*60*24) ) . " Days";
                }else{
                  $payment_note = "";
                  $days = "";
                }
                

                $invoice_tally_total = $invoice_amount + $invoice_tally_total;

              ?>

                <tr>
                  <td colspan="4"><?php echo $payment_date; ?> - <?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?> - <?php echo $payment_method; ?> - <?php echo $payment_reference; ?> - <?php echo $days; ?> <?php echo $payment_note; ?></td>
                </tr>
              <?php

              }

              ?>

            <?php 
            } 
            ?>

          </tbody>
        </table>
      </div>
    </div>

  <?php
  }
  ?>

<?php 
  }else{
    echo "GTFO";
  }
}else{
  echo "GTFO";
} 
?>

<?php include("guest_footer.php"); ?>
