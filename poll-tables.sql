-- Tables used by MediaWiki WikiPolls extension.
-- Vitaliy Filippov, 2009.
--
-- You should not have to create these tables manually unless you are doing
-- a manual installation. In normal conditions, maintenance/update.php should
-- perform any needed database setup.
--

--
-- Poll answers
--
CREATE TABLE IF NOT EXISTS /*$wgDBPrefix*/poll_vote (
  poll_id VARCHAR(32) NOT NULL,
  poll_user VARCHAR(255) NOT NULL,
  poll_ip VARCHAR(255),
  poll_answer INT(3),
  poll_date DATETIME,
  KEY `poll_id` (`poll_id`),
  KEY `poll_user` (`poll_user`)
) /*$wgDBTableOptions*/;
