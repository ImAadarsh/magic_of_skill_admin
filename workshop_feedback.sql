CREATE TABLE `workshop_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workshop_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `liked_most` text DEFAULT NULL,
  `future_topics` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
