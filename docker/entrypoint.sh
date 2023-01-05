#!/bin/bash
# Update the apache2 sites-available
echo "<Directory '/var/www/html'>
   Order allow,deny
   Allow from all
   Require all granted
</Directory>
<VirtualHost *:$ITFLOW_PORT>
    ServerName $ITFLOW_URL
    DocumentRoot /var/www/html/
    LogLevel $ITFLOW_LOG_LEVEL
    ErrorLog /var/log/apache2/error.log
    CustomLog /var/log/apache2/access.log combined
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

sed -i "s/^Listen.*/Listen $ITFLOW_PORT/g" /etc/apache2/ports.conf

# if itflow is not downloaded, perform the download after the volume mounting process within dockerfile is complete.
if [[ -f /var/www/html/index.php ]]; then 
    cd /var/www/html
    git fetch
else
    git clone --branch $ITFLOW_REPO_BRANCH https://$ITFLOW_REPO /var/www/html
fi

git config --global --add safe.directory /var/www/html

# Verify permissions of itflow git repository
chown -R www-data:www-data /var/www/html

# This updates the config.php file once initialization through setup.php has completed
if [[ -f /var/www/html/config.php ]]; then 
    # Company Name
    sed -i "s/\$config_app_name.*';/\$config_app_name = '$ITFLOW_NAME';/g" /var/www/html/config.php

    # MariaDB Host
    sed -i "s/\$dbhost.*';/\$dbhost = '$ITFLOW_DB_HOST';/g" /var/www/html/config.php

    # Database Password
    sed -i "s/\$dbpassword.*';/\$dbpassword = '$ITFLOW_DB_PASS';/g" /var/www/html/config.php

    # Base URL
    sed -i "s/\$config_base_url.*';/\$config_base_url = '$ITFLOW_URL';/g" /var/www/html/config.php
    
    find /var/www/html -type d -exec chmod 775 {} \;
    find /var/www/html -type f -exec chmod 664 {} \;
    chmod 640 /var/www/html/config.php
else 
    chmod -R 777 /var/www/html
fi

if [[-f /var/www/html/settings_update.php ]]; then 
    sed -i "s/\$repo_branch.*';/\$repo_branch = '$ITFLOW_REPO_BRANCH';/g" /var/www/html/settings_update.php
fi

# Enable the apache2 sites-available
service apache2 reload
service apache2 stop

# Execute the command in the dockerfile's CMD
exec "$@"