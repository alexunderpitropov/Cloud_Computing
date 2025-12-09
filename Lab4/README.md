# Лабораторная работа №5. Облачные базы данных AWS: Amazon RDS и DynamoDB

**Студент:** Питропов Александр

**Группа:** I2302 

---

## 1. Описание лабораторной работы

### 1.1 Постановка задачи

Цель лабораторной работы — изучить принципы работы облачных баз данных Amazon Web Services, а также научиться:

* Развёртывать реляционную СУБД MySQL в сервисе Amazon RDS.
* Настраивать сетевую инфраструктуру AWS (VPC, подсети, Security Groups).
* Создавать Subnet Group для выделенных приватных подсетей.
* Создавать Read Replica и проверять репликацию данных.
* Подключаться к базе данных RDS через EC2 и выполнять CRUD-операции.
* Использовать NoSQL‑базу данных Amazon DynamoDB и выполнять операции чтения/записи.

В итоге студент формирует комплексное понимание работы реляционных и нереляционных БД в облачной среде, а также их взаимодействия с приложениями.

---

## 2. Практическая часть

### 2.1 Шаг 1 — Подготовка сети: VPC, подсети и группы безопасности

#### 2.1.1 Создание VPC и подсетей

Была создана новая VPC `project-lab5-vpc` с CIDR-блоком `10.0.0.0/16`.

С помощью мастера **Create VPC → VPC and more** выполнено:

* Созданы две публичные подсети: `10.0.1.0/24`, `10.0.2.0/24`.
* Созданы две приватные подсети: `10.0.3.0/24`, `10.0.4.0/24`.
* Подсети распределены по разным зонам доступности (`eu-north-1a`, `eu-north-1b`).
* Включены `DNS Hostnames` и `DNS Resolution`.

#### 2.1.2 Создание Security Groups

**1. web-security-group** (для EC2):

* Inbound:

  * HTTP (80) — 0.0.0.0/0.
  * SSH (22) — 0.0.0.0/0 (учебный режим).
* Outbound:

  * MySQL (3306) → `db-mysql-security-group`.

**2. db-mysql-security-group** (для RDS):

* Inbound:

  * MySQL (3306) — только от `web-security-group`.

Это обеспечивает защищённый доступ к базе данных только из приложения.

---

### 2.2 Шаг 2 — Создание Subnet Group и развёртывание Amazon RDS

#### 2.2.1 Создание Subnet Group

Создана Subnet Group:

* Name: `project-rds-subnet-group`
* VPC: `project-lab5-vpc`
* Subnets: приватные подсети в двух AZ

**Контрольный вопрос:**

> *Subnet Group — логическая группа приватных подсетей, из которых RDS может выбирать место размещения. Нужна для изоляции базы данных в приватной зоне и обеспечения отказоустойчивости.*

#### 2.2.2 Создание экземпляра базы данных RDS MySQL

Настройки:

* Engine: MySQL 8.0.42
* Identifier: `project-rds-mysql-prod`
* Class: `db.t3.micro`
* Storage: 20 GB GP3 (+ autoscaling до 100 GB)
* Public access: No
* DB subnet group: `project-rds-subnet-group`
* Security Group: `db-mysql-security-group`
* Initial DB name: `project_db`
* Backups enabled

После статуса **Available** был скопирован endpoint базы данных.

---

### 2.3 Шаг 3 — Создание EC2 для подключения к RDS

Создан экземпляр EC2 Amazon Linux 2023:

* Type: `t3.micro`
* Subnet: публичная подсеть
* Security Group: `web-security-group`

User Data:

```bash
#!/bin/bash
dnf update -y
dnf install -y mariadb105
```

Виртуальная машина успешно развернута и готова к подключению.

---

### 2.4. Шаг 4 — Подключение к базе данных и выполнение CRUD

### 2.4.1. Подключение по SSH к EC2

Подключение к виртуальной машине EC2 выполнялось командой:

```bash
ssh -i lab5-key.pem ec2-user@<EC2_PUBLIC_IP>
```

gде `<EC2_PUBLIC_IP>` — публичный IP созданного EC2-инстанса.

---

### 2.4.2. Подключение к базе данных RDS

После входа на EC2 было выполнено подключение к MySQL-клиенту:

```bash
mysql -h <RDS_ENDPOINT> -u admin -p
```

После успешного входа выбрана база данных:

```sql
USE project_db;
```

---

### 2.4.3. Создание таблиц и связь one-to-many

Созданы две таблицы: `categories` и `todos`, связанные по схеме один-ко-многим.

```sql
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL
);

CREATE TABLE todos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category_id INT NOT NULL,
  status VARCHAR(50) NOT NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

**Логика:**

* одна категория может содержать множество задач;
* поле `category_id` является внешним ключом.

---

### 2.4.4. Вставка данных (INSERT)

```sql
INSERT INTO categories (name) VALUES
('Home'),
('Work'),
('Study');

INSERT INTO todos (title, category_id, status) VALUES
('Buy milk', 1, 'pending'),
('Finish lab report', 3, 'in-progress'),
('Deploy RDS instance', 2, 'pending'),
('Read about DynamoDB', 3, 'pending');
```

---

### 2.4.5. Запросы SELECT с JOIN

```sql
SELECT
  t.id,
  t.title,
  c.name AS category,
  t.status
FROM todos t
JOIN categories c ON t.category_id = c.id;
```

Пример UPDATE:

```sql
UPDATE todos SET status = 'done' WHERE id = 1;
```

---

## 2.5. Шаг 5 — Создание и тестирование Read Replica

Read Replica создана со следующими параметрами:

* Identifier: `project-rds-mysql-read-replica`
* Instance class: `db.t3.micro`
* Storage: gp3
* Public access: No
* Security group: db-mysql-security-group

Подключение:

```bash
mysql -h <REPLICA_ENDPOINT> -u admin -p
USE project_db;
```

### 2.5.1. Контрольные вопросы по Read Replica

**Какие данные видны при SELECT на реплике? Почему?**

На реплике отображаются те же данные, что и на основном экземпляре, потому что используется **асинхронная репликация MySQL**.

**Получилось ли выполнить запись (INSERT/UPDATE) на реплике? Почему?**

Операции записи недоступны, так как Read Replica работает **только для чтения**. AWS блокирует любые попытки изменения данных.

**Отобразилась ли новая запись на реплике после вставки на Primary?**

Да, с небольшой задержкой. Реплика получает изменения через бинарные логи.

**Зачем нужны Read Replicas?**

* масштабирование нагрузки на чтение;
* разгрузка основного экземпляра;
* аналитические запросы без влияния на продуктовую БД;
* повышение отказоустойчивости.

---

## 2.6. Шаг 6 — Подключение приложения к базе данных

Использован вариант 6b — приложение из предыдущей PHP-лабы.

Установка пакетов:

```bash
sudo dnf install -y httpd php php-mysqlnd
sudo systemctl enable --now httpd
```

Изменение настроек подключения:

```php
$dbHost = '<RDS_ENDPOINT>';
$dbName = 'project_db';
$dbUser = 'admin';
$dbPass = '********';
```

Приложение успешно выполняет все CRUD-операции.

Возможная архитектура:

* SELECT → Read Replica
* INSERT/UPDATE/DELETE → Primary RDS

---

## 2.7. Шаг 7 — Работа с Amazon DynamoDB

### 2.7.1. Проектирование таблицы

Создана таблица:

* Name: `Todos`
* Partition key: `id (String)`
* Sort key: отсутствует

### 2.7.2. Добавление данных

Примеры элементов:

```json
{
  "id": "task-1",
  "title": "Learn DynamoDB basics",
  "status": "pending"
}
```

```json
{
  "id": "task-2",
  "title": "Integrate app with RDS",
  "status": "done"
}
```

### 2.7.3. Контрольные вопросы по DynamoDB

**Преимущества DynamoDB:**

* отсутствие администрирования;
* автоматическое масштабирование;
* высокая скорость запросов;
* гибкая схема.

**Недостатки:**

* отсутствие JOIN;
* сложность моделирования данных;
* необходимость заранее планировать ключи и паттерны запросов.

**Сложности при проектировании:**

* необходимость денормализации данных;
* невозможность привычных связей через внешние ключи.

**Сценарий совместного использования RDS + DynamoDB:**

* RDS хранит транзакционные данные (пользователи, заказы);
* DynamoDB хранит быстрые данные: сессии, события, кэш.

Это улучшает производительность и снижает нагрузку на RDS.

---

## 3. Список использованных источников

* [https://docs.aws.amazon.com](https://docs.aws.amazon.com)
* [https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/)
* [https://docs.aws.amazon.com/amazondynamodb](https://docs.aws.amazon.com/amazondynamodb)
* [https://registry.terraform.io/providers/hashicorp/aws/latest/docs](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)

---

## 4. Вывод

В ходе работы были выполнены следующие задачи:

* создана облачная сеть VPC с подсетями и группами безопасности;
* развернут Amazon RDS MySQL в приватных подсетях;
* выполнено подключение с EC2 и выполнены CRUD-операции;
* создана и протестирована Read Replica;
* изучена работа NoSQL-хранилища DynamoDB;
* проанализированы сценарии совместного использования реляционных и нереляционных баз.

Лабораторная работа позволила сформировать чёткое понимание архитектуры облачных баз данных AWS и их практического применения.
