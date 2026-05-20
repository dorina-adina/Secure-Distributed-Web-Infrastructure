Secure and Distributed Web Infrastructure - Library App

*Project Overview*

This project demonstrates the design and implementation of a highly available, scalable, and secure containerized web infrastructure using Docker Compose. The architecture simulates a production-ready environment by decoupling services, enforcing network security, distributing traffic, and implementing database replication.

*Architecture & Component Breakdown*

The infrastructure is orchestrated using an 8-container ecosystem, where each component serves a single, dedicated responsibility:

- Reverse Proxy & Load Balancer (Nginx): Acts as the single entry point. It handles incoming traffic via HTTPS (port 8443), terminates SSL/TLS certificates, enforces Access Control Lists (ACL), and balances loads across the web application replicas.

- Web Application Layer (PHP-Apache/FPM): Consists of duplicated, stateless web containers (web1 and web2). This setup ensures High Availability; if one container fails, the other handles the traffic seamlessly.

- Session Management & Caching (Redis): Stores PHP user sessions globally. Since traffic is dynamically routed between web replicas, Redis ensures users remain authenticated regardless of which container handles their request.

- Database Cluster (MySQL Master-Slave): * DB_Master: Dedicated to write operations (INSERT, UPDATE, DELETE).

- DB_Slave: Replicates data from the Master in real-time and handles read operations (SELECT), optimizing performance and data redundancy.

- Database Management (phpMyAdmin): Provides a secure graphical interface to manage databases, monitor tables, and verify replication status.

- Centralized Logging (Dozzle): A container log viewer that consolidates real-time logs from all running containers into a single dashboard for easier debugging.

- Mail Testing Server (Mailpit): Captures outgoing emails sent by the application during development, preventing them from being sent to real users.

*Key Security & DevOps Features Implemented*

- High Availability (HA): Eliminates single points of failure at the web layer through replica scaling.

- Data Consistency: Maintained through a automated Master-Slave replication pipeline.

- Stateless Architecture: Achieved by offloading local session states to an external Redis key-value store.

- Network Security: End-to-end traffic encryption using TLS/SSL certificates and IP restriction via Nginx ACL configuration.
