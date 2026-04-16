# ใช้ PHP 8.2 พร้อม Apache
FROM php:8.2-apache

# ติดตั้ง Extension PDO MySQL สำหรับเชื่อมต่อฐานข้อมูล
RUN docker-php-ext-install pdo pdo_mysql

# เปิดใช้งาน mod_rewrite ของ Apache
RUN a2enmod rewrite