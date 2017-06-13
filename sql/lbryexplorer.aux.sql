-- Address Tagging TX tracking
CREATE TABLE `TagAddressRequests`
(
    `Id` SERIAL,
    `Address` VARCHAR(35) NOT NULL,
    `VerificationAmount` DECIMAL(18,8) NOT NULL,
    `Tag` VARCHAR(30) NOT NULL,
    `TagUrl` VARCHAR(200) NULL,
    `IsVerified` TINYINT(1) DEFAULT 0 NOT NULL,
    `Created` DATETIME NOT NULL,
    `Modified` DATETIME NOT NULL,
    PRIMARY KEY `PK_TagAddressRequest` (`Id`),
    UNIQUE KEY `Idx_TagAddressRequestId` (`Address`, `VerificationAmount`),
    INDEX `Idx_TagAddressRequestVerificationAmount` (`VerificationAmount`),
    INDEX `Idx_TagAddressRequestAddress` (`Address`),
    INDEX `Idx_TagAddressRequestCreated` (`Created`),
    INDEX `Idx_TagAddressRequestModified` (`Modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;