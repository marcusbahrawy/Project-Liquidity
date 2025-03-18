-- Add is_archived column to incoming table
ALTER TABLE incoming ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0;

-- Add is_archived column to outgoing table
ALTER TABLE outgoing ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0;

-- Create indexes for better performance
CREATE INDEX idx_incoming_archived ON incoming(is_archived);
CREATE INDEX idx_outgoing_archived ON outgoing(is_archived);

-- Create a stored procedure to automatically archive transactions
DELIMITER //

CREATE PROCEDURE archive_transactions()
BEGIN
    -- Archive incoming transactions
    UPDATE incoming 
    SET is_archived = 1 
    WHERE date < CURDATE() 
    AND is_archived = 0;
    
    -- Archive outgoing transactions
    UPDATE outgoing 
    SET is_archived = 1 
    WHERE date < CURDATE() 
    AND is_archived = 0;
END //

DELIMITER ;

-- Create an event to run the archive procedure daily
CREATE EVENT archive_transactions_event
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
    CALL archive_transactions(); 