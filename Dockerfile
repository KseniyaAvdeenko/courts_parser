FROM php:8.2-cli

# Устанавливаем curl
RUN apt-get update && apt-get install -y \
    curl \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/*


WORKDIR /app

COPY . /app

RUN chmod 777 /app

CMD ["php", "index.php"]
