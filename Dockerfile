FROM php:8.3-apache

# Webmap = public/, .htaccess toestaan, HTTPS herkennen achter de proxy
RUN a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo 'SetEnvIf X-Forwarded-Proto https HTTPS=on' > /etc/apache2/conf-enabled/proxy-https.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/storage/data \
 && chown -R www-data:www-data /var/www/html/storage
