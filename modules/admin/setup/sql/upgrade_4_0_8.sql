ALTER TABLE `coupons` CHANGE `coupons_discount` `coupons_discount` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `currency` CHANGE `rate` `rate` FLOAT(23,10) NOT NULL DEFAULT '1.0000000000';
ALTER TABLE `invoice` CHANGE `amount` `amount` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoice` CHANGE `subtotal` `subtotal` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoice` CHANGE `balance_due` `balance_due` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoice` CHANGE `tax` `tax` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoice` CHANGE `tax2` `tax2` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoiceentry` CHANGE `price` `price` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoiceentry` CHANGE `price_percent` `price_percent` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `invoicetransaction` CHANGE `amount` `amount` FLOAT(23,2) DEFAULT '0.00';
ALTER TABLE `package` CHANGE `price` `price` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `package` CHANGE `price3` `price3` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `package` CHANGE `price6` `price6` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `package` CHANGE `price12` `price12` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `package` CHANGE `price24` `price24` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `package` CHANGE `setup` `setup` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `packageaddon_prices` CHANGE `price0` `price0` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `packageaddon_prices` CHANGE `price1` `price1` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `packageaddon_prices` CHANGE `price3` `price3` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `packageaddon_prices` CHANGE `price6` `price6` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `packageaddon_prices` CHANGE `price12` `price12` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `packageaddon_prices` CHANGE `price24` `price24` FLOAT(23,2) NOT NULL DEFAULT '-1.00';
ALTER TABLE `recurringfee` CHANGE `amount` `amount` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `recurringfee` CHANGE `amount_percent` `amount_percent` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `taxrule` CHANGE `tax` `tax` FLOAT(23,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `users` CHANGE `balance` `balance` FLOAT(23,2) NOT NULL DEFAULT '0.00';