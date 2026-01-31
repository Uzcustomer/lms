#!/bin/bash

LOG_FILE="/var/log/backup_script.log"

echo "$(date): Backup boshlandi" >> $LOG_FILE

DATE=$(date +"%Y-%m-%d_%H-%M-%S")

if mysqldump -h localhost -u root  lmsttatf > /var/markbackups/lmsttatf_$DATE.sql; then
#if mysqldump -h localhost -u root -pPassword@123 lmsttatf > /var/markbackups/lmsttatf_$DATE.sql; then
    echo "$(date): Backup muvaffaqiyatli yaratildi" >> $LOG_FILE
else
    echo "$(date): Backup yaratishda xatolik yuz berdi" >> $LOG_FILE
fi

find /var/markbackups -name "lmsttatf_*.sql" -type f -mtime +10 -delete

echo "$(date): Backup jarayoni yakunlandi" >> $LOG_FILE
