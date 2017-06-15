DELIMITER //

CREATE PROCEDURE DeleteBlock (
    IN BlockId BIGINT
)
BEGIN
    START TRANSACTION;

    DELETE FROM InputsAddresses WHERE InputId IN (
        SELECT Id FROM Inputs WHERE TransactionId IN (
            SELECT Id FROM Transactions WHERE BlockHash IN (
                SELECT Hash FROM Blocks WHERE Id = BlockId
            )
        )
    );

    DELETE FROM OutputsAddresses WHERE OutputId IN (
        SELECT Id FROM Outputs WHERE TransactionId IN (
            SELECT Id FROM Transactions WHERE BlockHash IN (
                SELECT Hash FROM Blocks WHERE Id = BlockId
            )
        )
    );

    DELETE FROM Inputs WHERE TransactionId IN (
        SELECT Id FROM Transactions WHERE BlockHash IN (
            SELECT Hash FROM Blocks WHERE Id = BlockId
        )
    );

    DELETE FROM Outputs WHERE TransactionId IN (
        SELECT Id FROM Transactions WHERE BlockHash IN (
            SELECT Hash FROM Blocks WHERE Id = BlockId
        )
    );

    DELETE FROM TransactionsAddresses WHERE TransactionId IN (
        SELECT Id FROM Transactions WHERE BlockHash IN (
            SELECT Hash FROM Blocks WHERE Id = BlockId
        )
    );

    DELETE FROM Transactions WHERE BlockHash IN (
        SELECT Hash FROM Blocks WHERE Id = BlockId
    );

    DELETE FROM Blocks WHERE Id = BlockId;

    COMMIT;
END//

DELIMITER ;