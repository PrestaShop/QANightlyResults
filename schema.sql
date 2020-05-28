--
-- Table structure for table `execution`
--

CREATE TABLE `execution` (
  `id` int(11) NOT NULL,
  `ref` bigint(11) NOT NULL,
  `filename` varchar(50) NOT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `browser` varchar(30) NOT NULL DEFAULT 'chromium',
  `campaign` varchar(30) NOT NULL DEFAULT 'functional',
  `suites` int(11) DEFAULT NULL,
  `tests` int(11) DEFAULT NULL,
  `skipped` int(11) DEFAULT NULL,
  `pending` int(11) DEFAULT NULL,
  `passes` int(11) DEFAULT NULL,
  `failures` int(11) DEFAULT NULL,
  `insertion_start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `insertion_end_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(1, 'version', '2');

-- --------------------------------------------------------

--
-- Table structure for table `suite`
--

CREATE TABLE `suite` (
  `id` int(11) NOT NULL,
  `execution_id` int(11) NOT NULL,
  `uuid` varchar(50) NOT NULL,
  `title` text NOT NULL,
  `campaign` varchar(40) DEFAULT NULL,
  `file` varchar(200) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `hasSkipped` tinyint(1) DEFAULT NULL,
  `hasPending` tinyint(1) DEFAULT NULL,
  `hasPasses` tinyint(1) DEFAULT NULL,
  `hasFailures` tinyint(1) DEFAULT NULL,
  `totalSkipped` int(11) DEFAULT NULL,
  `totalPending` int(11) DEFAULT NULL,
  `totalPasses` int(11) DEFAULT NULL,
  `totalFailures` int(11) DEFAULT NULL,
  `hasSuites` int(11) DEFAULT NULL,
  `hasTests` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `insertion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `test`
--

CREATE TABLE `test` (
  `id` int(11) NOT NULL,
  `suite_id` int(11) NOT NULL,
  `uuid` varchar(50) NOT NULL,
  `title` text NOT NULL,
  `state` varchar(20) DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `error_message` text,
  `stack_trace` text,
  `diff` text,
  `insertion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for table `execution`
--
ALTER TABLE `execution`
  ADD PRIMARY KEY (`id`),
  ADD KEY `version` (`version`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suite`
--
ALTER TABLE `suite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `execution_id` (`execution_id`);

--
-- Indexes for table `test`
--
ALTER TABLE `test`
  ADD PRIMARY KEY (`id`),
  ADD KEY `state` (`state`),
  ADD KEY `id` (`id`),
  ADD KEY `suite_id` (`suite_id`);

-- AUTO_INCREMENT for table `execution`
--
ALTER TABLE `execution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `suite`
--
ALTER TABLE `suite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `test`
--
ALTER TABLE `test`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `suite`
--
ALTER TABLE `suite`
  ADD CONSTRAINT `suite_ibfk_1` FOREIGN KEY (`execution_id`) REFERENCES `execution` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `test`
--
ALTER TABLE `test`
  ADD CONSTRAINT `test_ibfk_1` FOREIGN KEY (`suite_id`) REFERENCES `suite` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
