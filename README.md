

# ENCG Events – University Room & Event Management System

A web application built with **Symfony** for managing **university events and room reservations** at **ENCG Agadir**.

The platform allows teachers, staff, and administrators to **schedule events, reserve rooms, and manage reservations through a centralized system**, eliminating conflicts and improving resource organization.

This project was developed as part of an academic internship.

---

# Project Overview

In many universities, room reservations are often handled manually or through decentralized communication, which can lead to:

* Reservation conflicts
* Lack of visibility on room availability
* Difficult coordination between departments
* No reservation history or tracking

This application provides a **digital solution** that centralizes the reservation process and offers a clear overview of upcoming events and room availability. 

---

# Features

## Public Access

* View upcoming university events
* View event details (date, room, organizer)

## Teacher / Staff

* Secure authentication
* Create events
* Reserve rooms
* Modify or delete reservations
* View reservation history
* Calendar view of reservations

## Administrator

* Manage users
* Manage rooms
* Manage events
* View all reservations
* Edit or delete events
* Full system supervision

---

# Technologies Used

This project uses modern web technologies:

* **Symfony** – Backend framework
* **PHP** – Backend development
* **Twig** – Template engine
* **Bootstrap** – User interface design
* **JavaScript** – Frontend interactions
* **MySQL** – Database management

The project follows the **MVC architecture**, separating the application into:

* Model (data layer)
* View (presentation layer)
* Controller (business logic) 

---

# Project Structure

The application follows the standard Symfony architecture:

```
project-root
│
├── assets/             # Frontend assets (JS, CSS)
├── bin/                # Symfony console commands
├── config/             # Configuration files
├── migrations/         # Database migrations
├── public/             # Application entry point
├── src/                # Application source code
│   ├── Controller/     # Controllers
│   ├── Entity/         # Database entities
│   ├── Repository/     # Database queries
│   └── Security/       # Authentication logic
├── templates/          # Twig templates
├── translations/       # Language files
├── var/                # Cache and logs
├── vendor/             # Composer dependencies
│
├── .env                # Environment configuration
├── composer.json       # PHP dependencies
└── symfony.lock        # Symfony dependency lock file
```

---

# Database Structure

Main entities used in the system:

```
User (id, email, roles, password, name)
Room (id, name)
Event (id, name, description, image)
Reservation (id, event_id, room_id, user_id, start_time, end_time)
```

These entities allow the system to manage:

* events
* rooms
* reservations
* users and roles

---

# Installation

Clone the repository:

```
git clone https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
```

Go to the project directory:

```
cd YOUR_REPOSITORY
```

Install dependencies:

```
composer install
```

Configure environment variables:

```
.env
```

Create the database:

```
php bin/console doctrine:database:create
```

Run migrations:

```
php bin/console doctrine:migrations:migrate
```

Start the server:

```
symfony server:start
```

Open the application:

```
http://127.0.0.1:8000
```

---

# Application Demo

The application includes multiple interfaces:

### Homepage

Displays upcoming events organized at ENCG Agadir.

### Authentication System

Secure login system for teachers and administrators.

### Teacher Dashboard

Allows users to:

* Manage reservations
* Create events
* View event calendar

### Admin Dashboard

Administrators can:

* Manage users
* Manage rooms
* Manage reservations
* View system activity

Screenshots of the application are available in the project report. 

---

# Skills Demonstrated

This project demonstrates skills in:

* Full-stack web development
* MVC architecture
* Database modeling
* Authentication & role management
* Backend development with Symfony
* Frontend design with Bootstrap
* Dynamic interaction with JavaScript
* Database integration with MySQL

---

# Project Context

This project was developed during an internship at:

**École Nationale de Commerce et de Gestion – Agadir (ENCG Agadir)**

Intern:
Ahmed Yassine El Ghazzali

Supervisor:
Mr. IZIMI ISSAM

Internship Period:
April 7 – May 26, 2024 

---

# License

This project was developed for **educational purposes**.

---
