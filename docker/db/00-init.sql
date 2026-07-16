CREATE DATABASE IF NOT EXISTS `dtr_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `zkbiotime` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create placeholder for corrupted table
USE `zkbiotime`;
CREATE TABLE IF NOT EXISTS `meeting_meetingtransaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_code` varchar(50) NOT NULL,
  `punch_datetime` datetime(6) NOT NULL,
  `punch_date` date NOT NULL,
  `punch_time` time(6) NOT NULL,
  `punch_state` varchar(5) NOT NULL,
  `source` smallint(6) NOT NULL,
  `upload_time` datetime(6) NOT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `meeting_id` int(11) DEFAULT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_id` (`emp_id`,`punch_datetime`),
  KEY `meeting_id` (`meeting_id`),
  KEY `terminal_id` (`terminal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
