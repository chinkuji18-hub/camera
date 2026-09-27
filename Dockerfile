FROM php:8.1-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy all project files to Apache root directory
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Render dynamically assigns a port, configure Apache to use it
ENV PORT=10000
RUN sed -i -e 's/80/10000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 10000
