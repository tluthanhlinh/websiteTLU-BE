# Sử dụng image PHP chính thức với Apache.
# Bạn có thể thay đổi phiên bản PHP (ví dụ: php:7.4-apache, php:8.2-apache)
# Đảm bảo phiên bản này tương thích với code PHP của bạn.
FROM php:8.1-apache

# Cài đặt các extension PHP cần thiết.
# mysqli: Để kết nối MySQL/MariaDB (nếu bạn dùng hàm mysqli_*)
# pdo_mysql: Để kết nối MySQL/MariaDB (nếu bạn dùng PDO)
# Bạn có thể cần thêm các extension khác tùy thuộc vào mã nguồn của bạn (ví dụ: gd, zip, intl, mbstring)
RUN docker-php-ext-install -j$(nproc) mysqli pdo_mysql

# Kích hoạt module rewrite của Apache.
# Điều này cần thiết nếu bạn sử dụng các quy tắc rewrite trong file .htaccess của mình.
RUN a2enmod rewrite

# Copy 000-default.conf vào đúng vị trí của Apache.
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
# Tắt site mặc định và kích hoạt site của chúng ta.
RUN a2dissite 000-default && a2ensite 000-default

# Copy toàn bộ mã nguồn của bạn vào thư mục web root của Apache trong container.
# '.' ở đây đại diện cho thư mục gốc của repository (websiteTLU-BE),
# '/var/www/html/' là thư mục mà Apache phục vụ mặc định bên trong container.
COPY . /var/www/html/

# Đặt quyền sở hữu cho người dùng Apache (www-data) để ứng dụng có thể đọc/ghi file.
RUN chown -R www-data:www-data /var/www/html

# Mở cổng mà Apache sẽ lắng nghe. Mặc định là 80.
EXPOSE 80

# Lệnh mặc định để khởi động Apache. Render sẽ sử dụng lệnh này.
# Render tự động chạy lệnh này, bạn không cần phải đặt Start Command trên Dashboard nữa.
CMD ["apache2-foreground"]
