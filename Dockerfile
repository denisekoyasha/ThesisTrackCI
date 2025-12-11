# Use official PHP CLI image
FROM php:8.2-cli

# Copy all project files into /app in the container
COPY . /app
WORKDIR /app

# Expose the port that Render expects
EXPOSE 10000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
