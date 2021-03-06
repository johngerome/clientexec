ALTER TABLE `troubleticket` ADD `hashed_id` VARCHAR(32) NOT NULL DEFAULT '' AFTER `id`;
ALTER TABLE `troubleticket` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `userid`;
ALTER TABLE `troubleticket` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `routingrules` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `routingrules` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `autoresponders` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `autoresponders` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `troubleticket_type` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `troubleticket_type` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `departments` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `departments` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `escalationrules` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `escalationrules` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `troubleticket_filters` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
DELETE FROM `permissions` WHERE `permission`='support_reopen_ticket';
ALTER TABLE `canned_response` ADD `company_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `canned_response` ADD INDEX `i_company_id` (`company_id`);
ALTER TABLE `troubleticket_log` ADD INDEX  `troubleticketid` (  `troubleticketid` );

UPDATE `troubleticket_type` set myorder=-1,enabled_public=0 where systemid > 0;