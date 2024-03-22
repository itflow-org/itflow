<?php

// Objects are to be the objects manipulated via the API
//

// Valid objects

$valid_objects = [
    'asset',
    'client',
    'invoice',
    'ticket',
    'location'
];

/*
------------------------
Object Parameters:
------------------------

* Denotes required field

Asset:
    Create:
        Parameters:
            *client_id = client_id(int)
            *asset_name = name(str)
            asset_description = description(str)
            *asset_type = Valid types are: 'Server', 'Desktop', 'Laptop', 'Tablet', 'Phone', 'Printer', 'Switch', 'Router', 'Firewall', 'Access Point', 'Other'
            asset_make = make(str)
            asset_model = model(str)
            asset_serial = serial(str)
            asset_os = os(str)
            asset_ip = ip(str)
            asset_nat_ip = nat_ip(str)
            asset_mac = mac(str)
            asset_uri = uri(str)
            asset_uri_2 = uri_2(str)
            asset_status = status(str)
            asset_location =   location(str)
            asset_vendor = vendor_id(int)
            asset_contact =  contact_id(int)
            asset_network =   network_id(int)
            asset_purchase_date = purchase_date(date)
            asset_warranty_expire =  warranty_expire(date)
            asset_install_date =  install_date(date)
            asset_notes = notes(str)

        Returns:
            status = success or error
            message = error message if status is error
            asset_id = asset_id(int)

    Read:
        Parameters:
            *asset_id = asset_id(int) or all(str)
        Returns:
            status = success or error
            message = error message if status is error
            asset_id = asset_id(int)
            client_id = client_id(int)
            asset_name = name(str)
            asset_description = description(str)
            asset_type = (str)
            asset_make = make(str)
            asset_model = model(str)
            asset_serial = serial(str)
            asset_os = os(str)
            asset_ip = ip(str)
            asset_nat_ip = nat_ip(str)
            asset_mac = mac(str)
            asset_uri = uri(str)
            asset_uri_2 = uri_2(str)
            asset_status = status(str)
            asset_location =   location(str)
            asset_vendor = vendor_id(int)
            asset_contact =  contact_id(int)
            asset_network =   network_id(int)
            asset_purchase_date = purchase_date(date)
            asset_warranty_expire =  warranty_expire(date)
            asset_install_date =  install_date(date)
            asset_notes = notes(str)

    Update:
        Parameters:
            *asset_id = asset_id(int)
            *client_id = client_id(int)
            asset_name = name(str)
            asset_description = description(str)
            asset_type = Valid types are: 'Server', 'Desktop', 'Laptop', 'Tablet', 'Phone', 'Printer', 'Switch', 'Router', 'Firewall', 'Access Point', 'Other'
            asset_make = make(str)
            asset_model = model(str)
            asset_serial = serial(str)
            asset_os = os(str)
            asset_ip = ip(str)
            asset_nat_ip = nat_ip(str)
            asset_mac = mac(str)
            asset_uri = uri(str)
            asset_uri_2 = uri_2(str)
            asset_status = status(str)
            asset_location =   location(str)
            asset_vendor = vendor_id(int)
            asset_contact =  contact_id(int)
            asset_network =   network_id(int)
            asset_purchase_date = purchase_date(date)
            asset_warranty_expire =  warranty_expire(date)
            asset_install_date =  install_date(date)
            asset_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            asset_id(int) = [
                client_id(int)
                asset_name(str)
                asset_description(str)
                asset_type(str)
                asset_make(str)
                asset_model(str)
                asset_serial(str)
                asset_os(str)
                asset_ip(str)
                asset_nat_ip(str)
                asset_mac(str)
                asset_uri(str)
                asset_uri_2(str)
                asset_status(str)
                asset_location(str)
                asset_vendor(int)
                asset_contact(int)
                asset_network(int)
                asset_purchase_date(date)
                asset_warranty_expire(date)
                asset_install_date(date)
                asset_notes(str)
            ]

    Delete:
        Parameters:
            *asset_id = asset_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            asset_id = asset_id(int)

client:
    Create:
        Parameters:
            *client_name = name(str)
            client_type = type(str)
            client_website = website(str)
            client_referral = referral(str)
            client_rate = rate(int)
            client_currency_code = currency_code(str)
            client_net_terms = net_terms(int)
            client_tax_id_number = tax_id_number(int)
            client_lead = lead(tinyint)
            client_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            client_id = client_id(int)
    Read:
        Parameters:
            *client_id = client_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            client_id = client_id(int)
            client_name = name(str)
            client_type = type(str)
            client_website = website(str)
            client_referral = referral(str)
            client_rate = rate(int)
            client_currency_code = currency_code(str)
            client_net_terms = net_terms(int)
            client_tax_id_number = tax_id_number(int)
            client_lead = lead(tinyint)
            client_notes = notes(str)

    Update:
        Parameters:
            *client_id = client_id(int)
            client_name = name(str)
            client_type = type(str)
            client_website = website(str)
            client_referral = referral(str)
            client_rate = rate(int)
            client_currency_code = currency_code(str)
            client_net_terms = net_terms(int)
            client_tax_id_number = tax_id_number(int)
            client_lead = lead(tinyint)
            client_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            client_id = client_id(int)
            client_name = name(str)
            client_type = type(str)
            client_website = website(str)
            client_referral = referral(str)
            client_rate = rate(int)
            client_currency_code = currency_code(str)
            client_net_terms = net_terms(int)
            client_tax_id_number = tax_id_number(int)
            client_lead = lead(tinyint)
            client_notes = notes(str)

    Delete:
        Parameters:
            *client_id = client_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            client_id = client_id(int)

Invoice:
    Create:
        Parameters:
            *client_id = client_id(int)
            *invoice_date = invoice_date(date)
            *invoice_due_date = invoice_due_date(date)
            *invoice_number = invoice_number(str)
            *invoice_amount = invoice_amount(int)
            *invoice_status = invoice_status(str)
            invoice_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            invoice_id = invoice_id(int)

    Read:
        Parameters:
            *invoice_id = invoice_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            invoice_id = invoice_id(int)
            client_id = client_id(int)
            invoice_date = invoice_date(date)
            invoice_due_date = invoice_due_date(date)
            invoice_number = invoice_number(str)
            invoice_amount = invoice_amount(int)
            invoice_status = invoice_status(str)
            invoice_notes = notes(str)

    Update:
        Parameters:
            *invoice_id = invoice_id(int)
            *client_id = client_id(int)
            invoice_date = invoice_date(date)
            invoice_due_date = invoice_due_date(date)
            invoice_number = invoice_number(str)
            invoice_amount = invoice_amount(int)
            invoice_status = invoice_status(str)
            invoice_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            invoice_id = invoice_id(int)
            client_id = client_id(int)
            invoice_date = invoice_date(date)
            invoice_due_date = invoice_due_date(date)
            invoice_number = invoice_number(str)
            invoice_amount = invoice_amount(int)
            invoice_status = invoice_status(str)
            invoice_notes = notes(str)

    Delete:
        Parameters:
            *invoice_id = invoice_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            invoice_id = invoice_id(int)


Ticket:
    Create:
        Parameters:
            *client_id = client_id(int)
            *ticket_date = ticket_date(date)
            *ticket_subject = ticket_subject(str)
            *ticket_description = ticket_description(str)
            ticket_status = ticket_status(str)
            ticket_priority = ticket_priority(str)
            ticket_notes = notes(str)
        Returns:
            status = success or error
            message = error message if status is error
            ticket_id = ticket_id(int)

    Read:
        Parameters:
            *ticket_id = ticket_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            ticket_id = ticket_id(int)
            client_id = client_id(int)
            ticket_date = ticket_date(date)
            ticket_subject = ticket_subject(str)
            ticket_description = ticket_description(str)
            ticket_status = ticket_status(str)
            ticket_priority = ticket_priority(str)
            ticket_notes = notes(str)

    Update:
        Parameters:
            *ticket_id = ticket_id(int)
            *client_id = client_id(int)
            ticket_prefix = 
            ticket_number =
            ticket_subject = 
            ticket_priority = 
            ticket_details = 
            ticket_billable = 
            ticket_vendor_ticket_number = 
            ticket_contact_id = 
            ticket_vendor_id = 
            ticket_asset_id = 
            ticket_number = 
            ticket_client_id = 
            ticket_source =
            ticket_category = 
            ticket_status = 
            ticket_schedule = 
            ticket_onsite = 
            ticket_feedback = 
            ticket_assigned_to = 
            ticket_invoice_id = 
            ticket_location_id = 
            ticket_closed_by = 

        Returns:
                

    Delete:
        Parameters:
            *ticket_id = ticket_id(int)
        Returns:
            status = success or error
            message = error message if status is error
            ticket_id = ticket_id(int)
*/