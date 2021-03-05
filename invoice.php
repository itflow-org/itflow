<?php 

include("header.php"); 

if(isset($_GET['invoice_id'])){

  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients, companies
    WHERE invoices.client_id = clients.client_id
    AND invoices.company_id = companies.company_id
    AND invoices.invoice_id = $invoice_id"
  );

  if(mysqli_num_rows($sql) == 0){
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1></center>";
  }else{

  $row = mysqli_fetch_array($sql);
  $invoice_id = $row['invoice_id'];
  $invoice_prefix = $row['invoice_prefix'];
  $invoice_number = $row['invoice_number'];
  $invoice_scope = $row['invoice_scope'];
  $invoice_status = $row['invoice_status'];
  $invoice_date = $row['invoice_date'];
  $invoice_due = $row['invoice_due'];
  $invoice_amount = $row['invoice_amount'];
  $invoice_currency_code = $row['invoice_currency_code'];
  $invoice_note = $row['invoice_note'];
  $invoice_url_key = $row['invoice_url_key'];
  $invoice_created_at = $row['invoice_created_at'];
  $category_id = $row['category_id'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_address = $row['client_address'];
  $client_city = $row['client_city'];
  $client_state = $row['client_state'];
  $client_zip = $row['client_zip'];
  $client_email = $row['client_email'];
  $client_phone = $row['client_phone'];
  if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
  }
  $client_extension = $row['client_extension'];
  $client_mobile = $row['client_mobile'];
  if(strlen($client_mobile)>2){ 
    $client_mobile = substr($row['client_mobile'],0,3)."-".substr($row['client_mobile'],3,3)."-".substr($row['client_mobile'],6,4);
  }
  $client_website = $row['client_website'];
  $client_currency_code = $row['client_currency_code'];
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }
  $company_name = $row['company_name'];
  $company_country = $row['company_country'];
  $company_address = $row['company_address'];
  $company_city = $row['company_city'];
  $company_state = $row['company_state'];
  $company_zip = $row['company_zip'];
  $company_phone = $row['company_phone'];
  if(strlen($company_phone)>2){ 
    $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
  }
  $company_email = $row['company_email'];
  $company_website = $row['company_website'];
  $company_logo = $row['company_logo'];
  $company_logo_base64 = base64_encode(file_get_contents($row['company_logo']));

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE invoice_id = $invoice_id ORDER BY history_id DESC");
  
  $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

  //Add up all the payments for the invoice and get the total amount paid to the invoice
  $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
  $row = mysqli_fetch_array($sql_amount_paid);
  $amount_paid = $row['amount_paid'];

  $balance = $invoice_amount - $amount_paid;

  //check to see if overdue
  if($invoice_status !== "Paid" AND $invoice_status !== "Draft" AND $invoice_status !== "Cancelled"){
    $unixtime_invoice_due = strtotime($invoice_due) + 86400;
    if($unixtime_invoice_due < time()){
      $invoice_overdue = "Overdue";
    }
  }
  
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

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="invoices.php">Invoices</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=invoices"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active"><?php echo "$invoice_prefix$invoice_number"; ?></li>
  <?php if(isset($invoice_overdue)){ ?>
  <span class="p-2 ml-2 badge badge-danger"><?php echo $invoice_overdue; ?></span>
  <?php } ?>
</ol>
  
<div class="card">
  
  <div class="card-header d-print-none">
    
    <div class="row">
      
      <div class="col-md-4">
        <?php if($invoice_status == 'Draft'){ ?>
        <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
          <i class="fas fa-fw fa-paper-plane"></i> Send
        </button>
        <div class="dropdown-menu">
          <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send Email</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="post.php?mark_invoice_sent=<?php echo $invoice_id; ?>">Mark Sent</a>
        </div>
        <?php } ?>

        <?php if($invoice_status !== 'Paid' and $invoice_status !== 'Cancelled' and $invoice_status !== 'Draft'){ ?>
        <a class="btn btn-success btn-sm" href="#" data-toggle="modal" data-target="#addPaymentModal"><i class="fa fa-fw fa-credit-card"></i> Add Payment</a>
        <?php } ?>
      </div>
      
      <div class="col-md-8">
        
        <div class="dropdown dropleft text-center">
          <button class="btn btn-primary btn-sm float-right" type="button" data-toggle="dropdown">
            <i class="fas fa-fw fa-ellipsis-v"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceRecurringModal<?php echo $invoice_id; ?>">Recurring</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" onclick="window.print();">Print</a>
            <a class="dropdown-item" href="#" onclick="pdfMake.createPdf(docDefinition).download('<?php echo "$invoice_date-$company_name-$client_name-Invoice-$invoice_prefix$invoice_number.pdf"; ?>');">Download PDF</a>
            <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send Email</a>
            
            <a class="dropdown-item" target="_blank" href="guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">Guest URL</a>
            <?php if($invoice_status !== 'Cancelled' and $invoice_status !== 'Paid'){ ?>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="post.php?cancel_invoice=<?php echo $invoice_id; ?>">Cancel</a>
            <?php } ?>
          </div>
        </div>
      
      </div>
    
    </div>
  
  </div>
  
  <div class="card-body">
      
    <div class="row mb-4">
      <div class="col-sm-2">
        <img class="img-fluid" src="<?php echo $company_logo; ?>">
      </div>
      <div class="col-sm-10">
        <div class="ribbon-wrapper">
          <div class="ribbon bg-<?php echo $invoice_badge_color; ?>">
            <?php echo $invoice_status; ?>
          </div>
        </div>
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
          <li><?php echo $company_website; ?></li>
        </ul>
      </div>
      <div class="col-sm">
        <ul class="list-unstyled text-right">
          <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
          <li><?php echo $client_address; ?></li>
          <li><?php echo "$client_city $client_state $client_zip"; ?></li>
          <li><?php echo "$client_phone $client_extension"; ?></li>
          <li><?php echo $client_mobile; ?></li>
          <li><?php echo $client_email; ?></li>
        </ul>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-sm-8">
      </div>
      <div class="col-sm-4">
        <table class="table">
          <tr>
            <td>Invoice Date</td>
            <td class="text-right"><?php echo $invoice_date; ?></td>
          </tr>
          <tr>
            <td>Due Date</td>
            <td class="text-right"><?php echo $invoice_due; ?></td>
          </tr>
        </table>
      </div>
    </div>

    <?php $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC"); ?>

    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <table class="table">
            <thead>
              <tr>
                <th class="d-print-none"></th>
                <th>Item</th>
                <th>Description</th>
                <th class="text-center">QTY</th>
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
                $item_name = $row['item_name'];
                $item_description = $row['item_description'];
                $item_quantity = $row['item_quantity'];
                $item_price = $row['item_price'];
                $item_subtotal = $row['item_price'];
                $item_tax = $row['item_tax'];
                $item_total = $row['item_total'];
                $item_created_at = $row['item_created_at'];
                $tax_id = $row['tax_id'];
                $total_tax = $item_tax + $total_tax;
                $sub_total = $item_price * $item_quantity + $sub_total;

              ?>

              <tr>
                <td class="text-center d-print-none">
                  <a class="text-secondary" href="#" data-toggle="modal" data-target="#editItemModal<?php echo $item_id; ?>"><i class="fa fa-fw fa-edit"></i></a>
                  <a class="text-danger" href="post.php?delete_invoice_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash-alt"></i></a>
                </td>
                <td><?php echo $item_name; ?></td>
                <td><?php echo $item_description; ?></td>
                <td class="text-center"><?php echo $item_quantity; ?></td>
                <td class="text-right">$<?php echo number_format($item_price,2); ?></td>
                <td class="text-right">$<?php echo number_format($item_tax,2); ?></td>
                <td class="text-right">$<?php echo number_format($item_total,2); ?></td>  
              </tr>

              <?php 

              include("edit_item_modal.php");

              }

              ?>

              <tr class="d-print-none">
                <form action="post.php" method="post" autocomplete="off">
                  <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                  <td></td>            
                  <td><input type="text" class="form-control" name="name" placeholder="Item"></td>
                  <td><textarea class="form-control" rows="2" name="description" placeholder="Description"></textarea></td>
                  <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: center;" name="qty" placeholder="QTY"></td>
                  <td><input type="number" step="0.01" class="form-control" style="text-align: right;" name="price" placeholder="Price"></td>
                  <td>             
                    <select class="form-control select2" name="tax_id" required>
                      <option value="0">None</option>
                      <?php 
                      
                      $taxes_sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE company_id = $session_company_id ORDER BY tax_name ASC"); 
                      while($row = mysqli_fetch_array($taxes_sql)){
                        $tax_id = $row['tax_id'];
                        $tax_name = $row['tax_name'];
                        $tax_percent = $row['tax_percent'];
                      ?>
                        <option value="<?php echo $tax_id; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                      
                      <?php
                      }
                      ?>
                    </select>
                  </td>
                  <td>
                    <button class="btn btn-link text-success" type="submit" name="add_invoice_item">
                      <i class="fa fa-fw fa-check"></i>
                    </button>
                  </td>
                </form>  
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-7">
        <div class="card">
          <div class="card-header">
            Notes
            <div class="card-tools d-print-none">
              <a href="#" class="btn btn-tool" data-toggle="modal" data-target="#invoiceNoteModal">
                <i class="fas fa-edit"></i>
              </a>
            </div>
          </div>
          <div class="card-body">
            <div><?php echo $invoice_note; ?></div>
          </div>
        </div>
      </div>
      <div class="col-3 offset-2">
        <table class="table table-borderless">
          <tbody>    
            <tr class="border-bottom">
              <td>Subtotal</td>
              <td class="text-right">$<?php echo number_format($sub_total,2); ?></td>
            </tr>
            <?php if($total_tax > 0){ ?>
            <tr class="border-bottom">
              <td>Tax</td>
              <td class="text-right">$<?php echo number_format($total_tax,2); ?></td>        
            </tr>
            <?php } ?>
            <?php if($amount_paid > 0){ ?>
            <tr class="border-bottom">
              <td><div class="text-success">Paid to Date</div></td>
              <td class="text-right text-success">$<?php echo number_format($amount_paid,2); ?></td>
            </tr>
            <?php } ?>
            <tr class="border-bottom">
              <td><strong>Balance Due</strong></td>
              <td class="text-right"><strong>$<?php echo number_format($balance,2); ?></strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <hr class="d-none d-print-block mt-5">

    <center class="d-none d-print-block"><?php echo $config_invoice_footer; ?></center>
  </div>
</div>

<div class="row d-print-none mb-3">
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        <i class="fa fa-fw fa-history"></i> History
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Status</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql_history)){
              $history_created_at = $row['history_created_at'];
              $history_status = $row['history_status'];
              $history_description = $row['history_description'];
             
            ?>
            <tr>
              <td><?php echo $history_created_at; ?></td>
              <td><?php echo $history_status; ?></td>
              <td><?php echo $history_description; ?></td>
            </tr>
            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-sm d-print-none">
    <div class="card">
      <div class="card-header">
        <i class="fa fa-fw fa-credit-card"></i> Payments
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th class="text-right">Amount</th>
              <th>Reference</th>
              <th>Account</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql_payments)){
              $payment_id = $row['payment_id'];
              $payment_date = $row['payment_date'];
              $payment_amount = $row['payment_amount'];
              $payment_reference = $row['payment_reference'];
              $account_name = $row['account_name'];

            ?>
            <tr>
              <td><?php echo $payment_date; ?></td>
              <td class=" text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
              <td><?php echo $payment_reference; ?></td>
              <td><?php echo $account_name; ?></td>
              <td class="text-center"><a class="btn btn-danger btn-sm" href="post.php?delete_payment=<?php echo $payment_id; ?>"><i class="fa fa-trash"></i></a></td>
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

<?php 
  include("add_payment_modal.php");
  include("add_invoice_copy_modal.php");
  include("add_invoice_recurring_modal.php");
  include("edit_invoice_modal.php");
  include("invoice_note_modal.php");
  include("add_quick_modal.php");
  
  } 
}

include("footer.php"); 

?>

<script src='plugins/pdfmake/pdfmake.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>

    <script>

    // Invoice markup
    // Author: Max Kostinevich
    // BETA (no styles)
    // http://pdfmake.org/playground.html
    // playground requires you to assign document definition to a variable called dd

    // CodeSandbox Example: https://codesandbox.io/s/pdfmake-invoice-oj81y


    var docDefinition = {
    info: {
    title: '<?php echo "$company_name - Invoice"; ?>',
    author: '<?php echo $company_name; ?>'
    },


    footer: {
    columns: [
      
      { text: '<?php echo $config_invoice_footer; ?>', style: 'documentFooterCenter' },
     
    ]
    },

    watermark: {text: '<?php echo $invoice_status; ?>', color: 'grey', opacity: 0.3, bold: true, italics: false},

    content: [
      // Header
      {
          columns: [
              {
                    image: '<?php echo "data:image;base64,$company_logo_base64"; ?>',
                    width: 120
              },
                  
              [
                  {
                      text: 'INVOICE', 
                      style: 'invoiceTitle',
                      width: '*'
                  },
                  {
                    stack: [
                         {
                             columns: [
                                  {
                                      text:'Invoice #', 
                                      style:'invoiceSubTitle',
                                      width: '*'
                                      
                                  }, 
                                  {
                                      text:'<?php echo "$invoice_prefix$invoice_number"; ?>',
                                      style:'invoiceSubValue',
                                      width: 100
                                      
                                  }
                                  ]
                         },
                         {
                             columns: [
                                 {
                                     text:'Date Issued',
                                     style:'invoiceSubTitle',
                                     width: '*'
                                 }, 
                                 {
                                     text:'<?php echo $invoice_date ?>',
                                     style:'invoiceSubValue',
                                     width: 100
                                 }
                                 ]
                         },
                         {
                             columns: [
                                 {
                                     text:'Due Date',
                                     style:'invoiceSubTitle',
                                     width: '*'
                                 }, 
                                 {
                                     text:'<?php echo $invoice_due ?>',
                                     style:'invoiceSubValue',
                                     width: 100
                                 }
                                 ]
                         },
                     ]
                  }
              ],
          ],
      },
      // Billing Headers
      {
          columns: [
              {
                  text: '<?php echo $company_name; ?>',
                  style:'invoiceBillingTitle',
                  
              },
              {
                  text: '<?php echo $client_name; ?>',
                  style:'invoiceBillingTitle',
                  
              },
          ]
      },
      
      {
          columns: [
              {
                  text: '<?php echo $company_address; ?> \n <?php echo "$company_city $company_state $company_zip"; ?> \n \n <?php echo $company_phone; ?> \n <?php echo $company_website; ?>',
                  style: 'invoiceBillingAddress'
              },
              {
                  text: '<?php echo $client_address; ?> \n <?php echo "$client_city $client_state $client_zip"; ?> \n \n <?php echo $client_email; ?> \n <?php echo $client_phone; ?>',
                  style: 'invoiceBillingAddress'
              },
          ]
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
                      style: 'itemsHeader'
                  }, 
                  {
                      text: 'Qty',
                      style: [ 'itemsHeader', 'center']
                  }, 
                  {
                      text: 'Price',
                      style: [ 'itemsHeader', 'center']
                  }, 
                  {
                      text: 'Tax',
                      style: [ 'itemsHeader', 'center']
                  }, 
                  {
                      text: 'Total',
                      style: [ 'itemsHeader', 'center']
                  } 
              ],
              // Items
              <?php 
              $total_tax = 0;
              $sub_total = 0;

              $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC");
              
              while($row = mysqli_fetch_array($sql_invoice_items)){
                $item_name = $row['item_name'];
                $item_description = $row['item_description'];
                $item_quantity = $row['item_quantity'];
                $item_price = $row['item_price'];
                $item_subtotal = $row['item_price'];
                $item_tax = $row['item_tax'];
                $item_total = $row['item_total'];
                $tax_id = $row['tax_id'];
                $total_tax = $item_tax + $total_tax;
                $total_tax = number_format($total_tax,2);
                $sub_total = $item_price * $item_quantity + $sub_total;
                $sub_total = number_format($sub_total, 2);
                echo "

                  // Item 1
                  [ 
                    [
                        {
                            text: '$item_name',
                            style:'itemTitle'
                        },
                        {
                            text: '$item_description',
                            style:'itemSubTitle'
                            
                        }
                    ], 
                    {
                        text:'$item_quantity',
                        style:'itemNumber'
                    }, 
                    {
                        text:'$$item_price',
                        style:'itemNumber'
                    }, 
                    {
                        text:'$$item_tax',
                        style:'itemNumber'
                    }, 
                    {
                        text: '$$item_total',
                        style:'itemTotal'
                    } 
                ],

                ";

              }

              ?>

              // END Items
            ]
          }, // table
        //  layout: 'lightHorizontalLines'
        },
     // TOTAL
        {
          table: {
            // headers are automatically repeated if the table spans over multiple pages
            // you can declare how many rows should be treated as headers
            headerRows: 0,
            widths: [ '*', 80 ],

            body: [
              // Total
              [ 
                  {
                      text:'Subtotal',
                      style:'itemsFooterSubTitle'
                  }, 
                  { 
                      text:'$<?php echo $sub_total; ?>',
                      style:'itemsFooterSubValue'
                  }
              ],
              [ 
                  {
                      text:'Tax',
                      style:'itemsFooterSubTitle'
                  },
                  {
                      text: '$<?php echo $total_tax; ?>',
                      style:'itemsFooterSubValue'
                  }
              ],
              [ 
                  {
                      text:'TOTAL',
                      style:'itemsFooterTotalTitle'
                  }, 
                  {
                      text: '$<?php echo $invoice_amount; ?>',
                      style:'itemsFooterTotalValue'
                  }
              ],
              [ 
                  {
                      text:'Paid',
                      style:'itemsFooterSubTitle'
                  },
                  {
                      text: '$<?php echo $amount_paid; ?>',
                      style:'itemsFooterSubValue'
                  }
              ],
              [ 
                  {
                      text:'Balance',
                      style:'itemsFooterSubTitle'
                  },
                  {
                      text: '$<?php echo number_format($balance,2); ?>',
                      style:'itemsFooterSubValue'
                  }
              ],
            ]
          }, // table
          layout: 'lightHorizontalLines'
        },
        { 
            text: 'NOTES',
            style:'notesTitle'
        },
        { 
            text: '<?php ?>',
            style:'notesText'
        }
    ],
    styles: {
      
    // Document Footer
    documentFooterCenter: {
        fontSize: 10,
        margin: [5,5,5,5],
        alignment:'center'
    },
    // Invoice Title
    invoiceTitle: {
      fontSize: 22,
      bold: true,
      alignment:'right',
      margin:[0,0,0,15]
    },
    // Invoice Details
    invoiceSubTitle: {
      fontSize: 12,
      alignment:'right'
    },
    invoiceSubValue: {
      fontSize: 12,
      alignment:'right'
    },
    // Billing Headers
    invoiceBillingTitle: {
      fontSize: 14,
      bold: true,
      alignment:'left',
      margin:[0,20,0,5],
    },
    // Billing Details
    invoiceBillingDetails: {
      alignment:'left'

    },
    invoiceBillingAddressTitle: {
        margin: [0,7,0,3],
        bold: true
    },
    invoiceBillingAddress: {
        
    },
    // Items Header
    itemsHeader: {
        margin: [0,5,0,5],
        bold: true
    },
    // Item Title
    itemTitle: {
        bold: true,
    },
    itemSubTitle: {
            italics: true,
            fontSize: 11
    },
    itemNumber: {
        margin: [0,5,0,5],
        alignment: 'center',
    },
    itemTotal: {
        margin: [0,5,0,5],
        bold: true,
        alignment: 'center',
    },

    // Items Footer (Subtotal, Total, Tax, etc)
    itemsFooterSubTitle: {
        margin: [0,5,0,5],
        bold: true,
        alignment:'right',
    },
    itemsFooterSubValue: {
        margin: [0,5,0,5],
        bold: true,
        alignment:'center',
    },
    itemsFooterTotalTitle: {
        margin: [0,5,0,5],
        bold: true,
        alignment:'right',
    },
    itemsFooterTotalValue: {
        margin: [0,5,0,5],
        bold: true,
        alignment:'center',
    },
    notesTitle: {
      fontSize: 10,
      bold: true,  
      margin: [0,50,0,3],
    },
    notesText: {
      fontSize: 10
    },
    center: {
        alignment:'center',
    },
    },
    defaultStyle: {
    columnGap: 20,
    }
    };
</script>

<script>

var products = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_name = $row['product_name'];
    echo "\"$product_name\",";
  }
  ?>

];

var productCosts2 = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_id, product_name, product_cost FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_id = $row['product_id'];
    $product_name = $row['product_name'];
    $product_cost = $row['product_cost'];
    echo "\"$product_cost\",";
  }
  ?>

];


var productCosts = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_id, product_name, product_cost FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_id = $row['product_id'];
    $product_name = $row['product_name'];
    $product_cost = $row['product_cost'];
    echo "{ id: '$product_id', name: '$product_name', cost: '$product_cost' },";
  }
  ?>

];

$('#item').typeahead({
  source: products,
  afterSelect: function(){
    $('#item').val( '<?php echo $product_name; ?>' );
  }

});


</script>