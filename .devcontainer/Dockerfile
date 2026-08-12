FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    default-mysql-client \
    gnupg \
    ca-certificates \
    lsb-release \
    apt-transport-https \
    && docker-php-ext-install zip pdo pdo_mysql && rm -rf /var/lib/apt/lists/*

# Install Composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

# Install Node.js (LTS 16.x)
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Create vscode user (used by Codespaces) and set permissions
ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=$USER_UID
RUN groupadd --gid $USER_GID $USERNAME || true \
    && useradd -m --uid $USER_UID --gid $USER_GID $USERNAME || true \
    && usermod -aG www-data $USERNAME || true

WORKDIR /workspace

# Ensure composer cache dir is writable
RUN mkdir -p /home/$USERNAME/.composer && chown -R $USERNAME:$USERNAME /home/$USERNAME/.composer

# Use non-root user by default
USER $USERNAME

CMD ["/bin/bash"]
