FROM php:8.1-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    zip \
    curl \
    bash \
    libzip \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    mysql-client \
    gnupg \
    ca-certificates \
    && docker-php-ext-install zip pdo pdo_mysql

# Install Composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

# Create vscode user (used by Codespaces) and set permissions
ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=$USER_UID
RUN addgroup -g $USER_GID $USERNAME 2>/dev/null || true \
    && adduser -D -u $USER_UID -G $USERNAME $USERNAME 2>/dev/null || true

WORKDIR /workspace

# Ensure composer cache dir is writable
RUN mkdir -p /home/$USERNAME/.composer && chown -R $USERNAME:$USERNAME /home/$USERNAME/.composer

# Use non-root user by default
USER $USERNAME

CMD ["/bin/bash"]
