CREATE TABLE `payment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `local_identifier` varchar(255) NOT NULL,
  `remote_identifier` varchar(255) NULL,
  `user_id` int(11) NOT NULL,
  `source_ils` varchar(255) NOT NULL,
  `cat_username` varchar(50) NOT NULL,
  `amount` int(11) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `service_fee` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `paid` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `registration_started` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `registered` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `status` int(1) NOT NULL DEFAULT '0',
  `status_message` varchar(255) NOT NULL DEFAULT '',
  `reported` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `local_identifier` (`local_identifier`),
  KEY `status_cat_username_created` (`status`, `cat_username`, `created`),
  KEY `paid_reported` (`paid`,`reported`),
  KEY `payment_user_id_idx` (`user_id`),
  CONSTRAINT `payment_ibfk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 collate utf8mb4_bin;

CREATE TABLE `payment_fee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `amount` int NOT NULL DEFAULT '0',
  `currency` varchar(3) NOT NULL,
  `fine_id` varchar(1024) NOT NULL DEFAULT '',
  `organization` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `payment_fee_payment_id_idx` (`payment_id`),
  CONSTRAINT `payment_fee_ibfk1` FOREIGN KEY (`payment_id`) REFERENCES `payment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 collate utf8mb4_unicode_ci;

CREATE TABLE `payment_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `server_ip` varchar(255),
  `server_name` varchar(255),
  `request_uri` varchar(1024),
  `message` varchar(255) NOT NULL,
  `data` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_event_payment_id_idx` (`payment_id`),
  CONSTRAINT `payment_event_ibfk1` FOREIGN KEY (`payment_id`) REFERENCES `payment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 collate utf8mb4_bin;
