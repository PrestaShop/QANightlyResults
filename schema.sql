CREATE TABLE `execution` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `ref` bigint(11) NOT NULL,
 `start_date` timestamp NULL DEFAULT NULL,
 `end_date` timestamp NULL DEFAULT NULL,
 `duration` int(11) NOT NULL,
 `version` varchar(20) NOT NULL,
 `suites` int(11) DEFAULT NULL,
 `tests` int(11) DEFAULT NULL,
 `skipped` int(11) DEFAULT NULL,
 `passes` int(11) DEFAULT NULL,
 `failures` int(11) DEFAULT NULL,
 `insertion_start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `insertion_end_date` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `version` (`version`),
 KEY `execution_id` (`ref`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=latin1;

CREATE TABLE `suite` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `execution_id` int(11) NOT NULL,
 `uuid` varchar(50) NOT NULL,
 `title` varchar(1000) NOT NULL,
 `campaign` varchar(40) DEFAULT NULL,
 `file` varchar(200) DEFAULT NULL,
 `duration` int(11) DEFAULT NULL,
 `hasSkipped` tinyint(1) DEFAULT NULL,
 `hasPasses` tinyint(1) DEFAULT NULL,
 `hasFailures` tinyint(1) DEFAULT NULL,
 `totalSkipped` int(11) DEFAULT NULL,
 `totalPasses` int(11) DEFAULT NULL,
 `totalFailures` int(11) DEFAULT NULL,
 `hasSuites` int(11) DEFAULT NULL,
 `hasTests` int(11) DEFAULT NULL,
 `parent_id` int(11) DEFAULT NULL,
 `insertion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `execution_id` (`execution_id`),
 CONSTRAINT `suite_ibfk_1` FOREIGN KEY (`execution_id`) REFERENCES `execution` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46833 DEFAULT CHARSET=latin1;

CREATE TABLE `test` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `suite_id` int(11) NOT NULL,
 `uuid` varchar(50) NOT NULL,
 `title` varchar(1000) NOT NULL,
 `state` varchar(20) DEFAULT NULL,
 `duration` int(11) NOT NULL,
 `error_message` varchar(1000) DEFAULT NULL,
 `stack_trace` text,
 `diff` text,
 `insertion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 KEY `state` (`state`),
 KEY `id` (`id`),
 KEY `suite_id` (`suite_id`),
 CONSTRAINT `test_ibfk_1` FOREIGN KEY (`suite_id`) REFERENCES `suite` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=312039 DEFAULT CHARSET=latin1;