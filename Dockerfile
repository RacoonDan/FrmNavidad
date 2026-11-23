FROM php:8.2-cli

# Instalar extensión para MySQL
RUN docker-php-ext-install mysqli

# Copiar los archivos del proyecto
COPY . /var/www/html/

# Iniciar el servidor PHP usando el puerto de Railway
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
