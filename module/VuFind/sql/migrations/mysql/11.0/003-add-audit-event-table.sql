CREATE TABLE `audit_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(50) NOT NULL,
  `subtype` varchar(50) NOT NULL,
  `user_id` int,
  `username` varchar(255),
  `client_ip` varchar(255),
  `server_ip` varchar(255),
  `server_name` varchar(255),
  `message`  varchar(255),
  `data` mediumtext NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `audit_event_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
