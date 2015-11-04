-- Tables used by MediaWiki WikiPolls extension - PostgreSQL.
-- Vitaliy Filippov, 2015.
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
  poll_answer INT,
  poll_date TIMESTAMP WITH TIME ZONE NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*$wgDBPrefix*/poll_vote_poll_id ON /*$wgDBPrefix*/poll_vote (poll_id);
CREATE INDEX /*$wgDBPrefix*/poll_vote_poll_user ON /*$wgDBPrefix*/poll_vote (poll_user);
