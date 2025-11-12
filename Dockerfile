# Usa a imagem oficial do PHP com servidor embutido
FROM php:8.2-cli

# Copia todos os arquivos do repositório para dentro do container
COPY . /var/www/html

# Define o diretório de trabalho
WORKDIR /var/www/html

# Expõe a porta 10000 (que o Render usa por padrão)
EXPOSE 10000

# Comando para iniciar o servidor PHP
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]