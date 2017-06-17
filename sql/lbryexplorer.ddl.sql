l--DROP DATABASE IF EXISTS lbry;
CREATE DATABASE lbry DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lbry;

CREATE TABLE `Blocks`
(
    `Id` SERIAL,

    `Bits` VARCHAR(20) NOT NULL,
    `Chainwork` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `Confirmations` INTEGER UNSIGNED NOT NULL,
    `Difficulty` DECIMAL(18,8) NOT NULL,
    `Hash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL ,
    `Height` BIGINT UNSIGNED NOT NULL,
    `MedianTime` BIGINT UNSIGNED NOT NULL,
    `MerkleRoot` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `NameClaimRoot` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `Nonce` BIGINT UNSIGNED NOT NULL,
    `PreviousBlockHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `NextBlockHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `BlockSize` BIGINT UNSIGNED NOT NULL,
    `Target` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `BlockTime` BIGINT UNSIGNED NOT NULL,
    `Version` BIGINT UNSIGNED NOT NULL,
    `VersionHex` VARCHAR(10) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `TransactionHashes` TEXT,
    `TransactionsProcessed` TINYINT(1) DEFAULT 0 NOT NULL,

    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,

    PRIMARY KEY `PK_Block` (`Id`),
    UNIQUE KEY `Idx_BlockHash` (`Hash`),
    CONSTRAINT `Cnt_TransactionHashesValidJson` CHECK(`TransactionHashes` IS NULL OR JSON_VALID(`TransactionHashes`)),
    INDEX `Idx_BlockHeight` (`Height`),
    INDEX `Idx_BlockTime` (`BlockTime`),
    INDEX `Idx_MedianTime` (`MedianTime`),
    INDEX `Idx_PreviousBlockHash` (`PreviousBlockHash`),
    INDEX `Idx_BlockCreated` (`Created`),
    INDEX `Idx_BlockModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `Transactions`
(
    `Id` SERIAL,
    `BlockHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `InputCount` INTEGER UNSIGNED NOT NULL,
    `OutputCount` INTEGER UNSIGNED NOT NULL,
    `Value` DECIMAL(18,8) NOT NULL,
    `Fee` DECIMAL(18,8) DEFAULT 0 NOT NULL,
    `TransactionTime` BIGINT UNSIGNED,
    `TransactionSize` BIGINT UNSIGNED NOT NULL,
    `Hash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `Version` INTEGER NOT NULL,
    `LockTime` INTEGER UNSIGNED NOT NULL,
    `Raw` TEXT,
    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    `CreatedTime` INTEGER UNSIGNED DEFAULT UNIX_TIMESTAMP() NOT NULL,
    PRIMARY KEY `PK_Transaction` (`Id`),
    FOREIGN KEY `FK_TransactionBlockHash` (`BlockHash`) REFERENCES `Blocks` (`Hash`),
    UNIQUE KEY `Idx_TransactionHash` (`Hash`),
    INDEX `Idx_TransactionTime` (`TransactionTime`),
    INDEX `Idx_TransactionCreatedTime` (`CreatedTime`),
    INDEX `Idx_TransactionCreated` (`Created`),
    INDEX `Idx_TransactionModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `Addresses`
(
    `Id` SERIAL,
    `Address` VARCHAR(40) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `FirstSeen` DATETIME,
    `TotalReceived` DECIMAL(18,8) DEFAULT 0 NOT NULL,
    `TotalSent` DECIMAL(18,8) DEFAULT 0 NOT NULL,
    `Tag` VARCHAR(30) NOT NULL,
    `TagUrl` VARCHAR(200),
    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    PRIMARY KEY `PK_Address` (`Id`),
    UNIQUE KEY `Idx_AddressAddress` (`Address`),
    UNIQUE KEY `Idx_AddressTag` (`Tag`),
    INDEX `Idx_AddressTotalReceived` (`TotalReceived`),
    INDEX `Idx_AddressTotalSent` (`TotalSent`),
    INDEX `Idx_AddressCreated` (`Created`),
    INDEX `Idx_AddressModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `Inputs`
(
    `Id` SERIAL,
    `TransactionId` BIGINT UNSIGNED NOT NULL,
    `TransactionHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `AddressId` BIGINT UNSIGNED,
    `IsCoinbase` TINYINT(1) DEFAULT 0 NOT NULL,
    `Coinbase` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `PrevoutHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `PrevoutN` INTEGER UNSIGNED,
    `PrevoutSpendUpdated` TINYINT(1) DEFAULT 0 NOT NULL,
    `Sequence` INTEGER UNSIGNED,
    `Value` DECIMAL(18,8),
    `ScriptSigAsm` TEXT CHARACTER SET latin1 COLLATE latin1_general_ci,
    `ScriptSigHex` TEXT CHARACTER SET latin1 COLLATE latin1_general_ci,
    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    PRIMARY KEY `PK_Input` (`Id`),
    FOREIGN KEY `FK_InputAddress` (`AddressId`) REFERENCES `Addresses` (`Id`),
    FOREIGN KEY `FK_InputTransaction` (`TransactionId`) REFERENCES `Transactions` (`Id`),
    INDEX `Idx_InputValue` (`Value`),
    INDEX `Idx_PrevoutHash` (`PrevoutHash`),
    INDEX `Idx_InputCreated` (`Created`),
    INDEX `Idx_InputModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `InputsAddresses`
(
    `InputId` BIGINT UNSIGNED NOT NULL,
    `AddressId` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY `PK_InputAddress` (`InputId`, `AddressId`),
    FOREIGN KEY `Idx_InputsAddressesInput` (`InputId`) REFERENCES `Inputs` (`Id`),
    FOREIGN KEY `Idx_InputsAddressesAddress` (`AddressId`) REFERENCES `Addresses` (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `Outputs`
(
    `Id` SERIAL,
    `TransactionId` BIGINT UNSIGNED NOT NULL,
    `Value` DECIMAL(18,8),
    `Vout` INTEGER UNSIGNED,
    `Type` VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `ScriptPubKeyAsm` TEXT CHARACTER SET latin1 COLLATE latin1_general_ci,
    `ScriptPubKeyHex` TEXT CHARACTER SET latin1 COLLATE latin1_general_ci,
    `RequiredSignatures` INTEGER UNSIGNED,
    `Hash160` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `Addresses` TEXT CHARACTER SET latin1 COLLATE latin1_general_ci,
    `IsSpent` TINYINT(1) DEFAULT 0 NOT NULL,
    `SpentByInputId` BIGINT UNSIGNED,
    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    PRIMARY KEY `PK_Output` (`Id`),
    FOREIGN KEY `FK_OutputTransaction` (`TransactionId`) REFERENCES `Transactions` (`Id`),
    FOREIGN KEY `FK_OutputSpentByInput` (`SpentByInputId`) REFERENCES `Inputs` (`Id`),
    CONSTRAINT `Cnt_AddressesValidJson` CHECK(`Addresses` IS NULL OR JSON_VALID(`Addresses`)),
    INDEX `Idx_OutputValue` (`Value`),
    INDEX `Idx_OuptutCreated` (`Created`),
    INDEX `Idx_OutputModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `OutputsAddresses`
(
    `OutputId` BIGINT UNSIGNED NOT NULL,
    `AddressId` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY `PK_OutputAddress` (`OutputId`, `AddressId`),
    FOREIGN KEY `Idx_OutputsAddressesOutput` (`OutputId`) REFERENCES `Outputs` (`Id`),
    FOREIGN KEY `Idx_OutputsAddressesAddress` (`AddressId`) REFERENCES `Addresses` (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `TransactionsAddresses`
(
    `TransactionId` BIGINT UNSIGNED NOT NULL,
    `AddressId` BIGINT UNSIGNED NOT NULL,
    `DebitAmount` DECIMAL(18,8) DEFAULT 0 NOT NULL COMMENT 'Sum of the inputs to this address for the tx',
    `CreditAmount` DECIMAL(18,8) DEFAULT 0 NOT NULL COMMENT 'Sum of the outputs to this address for the tx',
    `TransactionTime` DATETIME DEFAULT UTC_TIMESTAMP() NOT NULL,
    PRIMARY KEY `PK_TransactionAddress` (`TransactionId`, `AddressId`),
    FOREIGN KEY `Idx_TransactionsAddressesTransaction` (`TransactionId`) REFERENCES `Transactions` (`Id`),
    FOREIGN KEY `Idx_TransactionsAddressesAddress` (`AddressId`) REFERENCES `Addresses` (`Id`),
    INDEX `Idx_TransactionsAddressesLatestTransactionTime` (`LatestTransactionTime`),
    INDEX `Idx_TransactionsAddressesDebit` (`DebitAmount`),
    INDEX `Idx_TransactionsAddressesCredit` (`CreditAmount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `Claims`
(
    `Id` SERIAL,
    `TransactionHash` VARCHAR(70) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `Vout` INTEGER UNSIGNED NOT NULL,
    `Name` VARCHAR(1024) NOT NULL,
    `ClaimId` CHAR(40) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
    `ClaimType` TINYINT(1) NOT NULL, -- 1 - CertificateType, 2 - StreamType
    `PublisherId` CHAR(40) CHARACTER SET latin1 COLLATE latin1_general_ci COMMENT 'references a ClaimId with CertificateType',
    `PublisherSig` VARCHAR(200) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `Certificate` TEXT,
    `TransactionTime` INTEGER UNSIGNED,
    `Version` VARCHAR(10) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,

    -- Additional fields for easy indexing of stream types
    `Author` VARCHAR(512),
    `Description` MEDIUMTEXT,
    `ContentType` VARCHAR(162) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `IsNSFW` TINYINT(1) DEFAULT 0 NOT NULL,
    `Language` VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_general_ci,
    `ThumbnailUrl` TEXT,
    `Title` TEXT,
    `Fee` DECIMAL(18,8) DEFAULT 0 NOT NULL,
    `FeeCurrency` CHAR(3),

    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    PRIMARY KEY `PK_Claim` (`Id`),
    FOREIGN KEY `FK_ClaimTransaction` (`TransactionHash`) REFERENCES `Transactions` (`Hash`),
    FOREIGN KEY `FK_ClaimPublisher` (`PublisherId`) REFERENCES `Claims` (`ClaimId`),
    CONSTRAINT `Cnt_ClaimCertificate` CHECK(`Certificate` IS NULL OR JSON_VALID(`Certificate`)), -- certificate type
    INDEX `Idx_Claim` (`ClaimId`),
    INDEX `Idx_ClaimTransactionTime` (`TransactionTime`),
    INDEX `Idx_ClaimCreated` (`Created`),
    INDEX `Idx_ClaimModified` (`Modified`),

    INDEX `Idx_ClaimAuthor` (`Author`(191)),
    INDEX `Idx_ClaimContentType` (`ContentType`),
    INDEX `Idx_ClaimLanguage` (`Language`),
    INDEX `Idx_ClaimTitle` (`Title`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `ClaimStreams`
(
    `Id` BIGINT UNSIGNED NOT NULL,
    `Stream` MEDIUMTEXT NOT NULL,
    PRIMARY KEY `PK_ClaimStream` (`Id`),
    FOREIGN KEY `PK_ClaimStreamClaim` (`Id`) REFERENCES `Claims` (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

CREATE TABLE `PriceHistory`
(
    `Id` SERIAL,
    `BTC` DECIMAL(18,8) DEFAULT 0 NOT NULL,
    `USD` DECIMAL(18,2) DEFAULT 0 NOT NULL,
    `Created` DATETIME NOT NULL,
    PRIMARY KEY `PK_PriceHistory` (`Id`),
    UNIQUE KEY `Idx_PriceHistoryCreated` (`Created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;
