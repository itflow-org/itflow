<?php

include("config.php");
include("functions.php");

if(isset($_GET['download_invoice'], $_GET['url_key'])){

    $invoice_id = intval($_GET['download_invoice']);
    $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients, companies, settings
    WHERE invoices.client_id = clients.client_id
    AND invoices.company_id = companies.company_id
    AND settings.company_id = companies.company_id
    AND invoices.invoice_id = $invoice_id
    AND invoices.invoice_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){

        $row = mysqli_fetch_array($sql);

        $invoice_id = $row['invoice_id'];
        $invoice_prefix = $row['invoice_prefix'];
        $invoice_number = $row['invoice_number'];
        $invoice_status = $row['invoice_status'];
        $invoice_date = $row['invoice_date'];
        $invoice_due = $row['invoice_due'];
        $invoice_amount = $row['invoice_amount'];
        $invoice_note = $row['invoice_note'];
        $invoice_category_id = $row['category_id'];
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
        $client_website = $row['client_website'];
        $company_id = $row['company_id'];
        $company_name = $row['company_name'];
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
        $company_logo = base64_encode(file_get_contents($row['company_logo']));
        $config_invoice_footer = $row['config_invoice_footer'];

        //Mark downloaded in history
        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'Invoice downloaded', history_created_at = NOW(), invoice_id = $invoice_id, company_id = $company_id");

        $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_amount_paid);
        $amount_paid = $row['amount_paid'];
        $amount_paid = number_format($amount_paid, 2);

        $balance = $invoice_amount - $amount_paid;
        $balance = number_format($balance, 2);

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
                        image: '<?php echo "data:image;base64,$company_logo"; ?>',
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
                          text: '$<?php echo $balance; ?>',
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

        pdfMake.createPdf(docDefinition).download('<?php echo "$invoice_date-$company_name-Invoice-$invoice_prefix$invoice_number.pdf"; ?>');

        </script>

    <?php

    }else{
        echo "GTFO!!!";
    }
}

if(isset($_GET['download_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['download_quote']);
    $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients, companies, settings
    WHERE quotes.client_id = clients.client_id
    AND quotes.company_id = companies.company_id
    AND settings.company_id = companies.company_id
    AND quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){
        $row = mysqli_fetch_array($sql);
        
        $quote_id = $row['quote_id'];
        $quote_prefix = $row['quote_prefix'];
        $quote_number = $row['quote_number'];
        $quote_status = $row['quote_status'];
        $quote_date = $row['quote_date'];
        $quote_amount = $row['quote_amount'];
        $quote_note = $row['quote_note'];
        $quote_url_key = $row['quote_url_key'];
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
        $client_website = $row['client_website'];
        $company_id = $row['company_id'];
        $company_name = $row['company_name'];
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
        $company_logo = base64_encode(file_get_contents($row['company_logo']));
        $config_quote_footer = $row['config_quote_footer'];

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
        title: '<?php echo "$company_name - Quote"; ?>',
        author: '<?php echo $company_name; ?>'
        },


        footer: {
        columns: [
          
          { text: '<?php echo $config_quote_footer; ?>', style: 'documentFooterCenter' },
         
        ]
        },

        watermark: {text: '<?php echo $quote_status; ?>', color: 'grey', opacity: 0.3, bold: true, italics: false},

        content: [
          // Header
          {
              columns: [
                  {
                        image: '<?php echo "data:image;base64,$company_logo"; ?>',
                        width: 120
                  },
                      
                  [
                      {
                          text: 'QUOTE', 
                          style: 'invoiceTitle',
                          width: '*'
                      },
                      {
                        stack: [
                             {
                                 columns: [        
                                      {
                                          text:'<?php echo "$quote_prefix$quote_number"; ?>',
                                          style:'invoiceSubValue',
                                          width: '*'
                                          
                                      },
                                      ]
                             },
                             {
                                 columns: [
                                     {
                                         text:'<?php echo $quote_date ?>',
                                         style:'invoiceSubValue',
                                         width: '*'
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

                  $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC");
                  
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
                          text: '$<?php echo $quote_amount; ?>',
                          style:'itemsFooterTotalValue'
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

        pdfMake.createPdf(docDefinition).download('<?php echo "$quote_date-$company_name-QUOTE-$quote_prefix$quote_number.pdf"; ?>');

        </script>

    <?php

    }else{
        echo "GTFO!!!";
    }
}

if(isset($_GET['accept_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['accept_quote']);
    $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    WHERE quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){
    
        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Accepted', history_description = 'Client accepted Quote!', history_created_at = NOW(), quote_id = $quote_id, company_id = $company_id");

        $_SESSION['alert_message'] = "Quote Accepted";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        echo "GTFO!!";
    }

}

if(isset($_GET['decline_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['decline_quote']);
    $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    WHERE quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){

        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Declined', history_description = 'Client declined Quote!', history_created_at = NOW(), quote_id = $quote_id, company_id = $company_id");

        $_SESSION['alert_message'] = "Quote Declined";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        echo "GTFO!!";
    }
    
}

?>  