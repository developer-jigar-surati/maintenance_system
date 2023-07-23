-- phpMyAdmin SQL Dump
-- version 4.6.6deb5ubuntu0.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 14, 2021 at 05:07 PM
-- Server version: 5.7.33-0ubuntu0.18.04.1
-- PHP Version: 7.2.24-0ubuntu0.18.04.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `apt_maintenance`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_auth`
--

CREATE TABLE `admin_auth` (
  `z_authid_pk` int(11) NOT NULL,
  `auth_id` varchar(255) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `expire_on` datetime NOT NULL,
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `admin_log`
--

CREATE TABLE `admin_log` (
  `z_logid_pk` int(11) NOT NULL,
  `log_id` varchar(255) NOT NULL DEFAULT '',
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `log_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1=login,2=logout',
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `admin_mst`
--

CREATE TABLE `admin_mst` (
  `z_adminid_pk` int(11) NOT NULL,
  `admin_id` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `email_id` varchar(255) NOT NULL DEFAULT '',
  `mobile_no` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '123',
  `building_id` varchar(255) DEFAULT NULL,
  `user_role` tinyint(1) DEFAULT NULL COMMENT '1=admin,2=president,3=flat holder',
  `forgotlink` varchar(255) DEFAULT NULL,
  `forgotlink_expire` datetime DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1=active,0=inactive',
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admin_mst`
--

INSERT INTO `admin_mst` (`z_adminid_pk`, `admin_id`, `name`, `email_id`, `mobile_no`, `password`, `building_id`, `user_role`, `forgotlink`, `forgotlink_expire`, `is_active`, `added_on`, `added_by`, `modified_on`, `modified_by`, `deleted_on`, `deleted_by`) VALUES
(1, '156f41ed978dfc318bce21fb240d30df', 'Admin', 'developerjigarsurati@gmail.com', '8733051014', '202cb962ac59075b964b07152d234b70', NULL, 1, 'http://localhost:8000/a@dmin/forgotpassword/a384163d90791d19980e0cdbdeb67a44', '2021-02-14 11:58:30', 1, '2020-12-11 19:51:29', NULL, '2021-02-14 11:58:43', 1, NULL, NULL),
(2, 'a83cb6e9655c7c708c6f49533a614cb5', 'Jigar', 'jigarsurati123@gmail.com', '8733051014', '202cb962ac59075b964b07152d234b70', 'f189ab57f4fe838b4713187afac2fc99', 2, NULL, NULL, 1, '2021-02-13 11:42:14', 1, NULL, NULL, NULL, NULL),
(3, 'db7bd52db5355e76800d89e3898d1b0a', 'Surati', 'jigarsurati111@gmail.com', '9876543210', '202cb962ac59075b964b07152d234b70', 'f189ab57f4fe838b4713187afac2fc99', 3, NULL, NULL, 1, '2021-02-13 11:46:17', 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_buildings`
--

CREATE TABLE `tbl_buildings` (
  `z_buildingid_pk` int(11) NOT NULL,
  `building_id` varchar(255) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `total_flats` int(11) NOT NULL,
  `maintenance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `description` text,
  `is_active` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1=active,0=inactive',
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_buildings`
--

INSERT INTO `tbl_buildings` (`z_buildingid_pk`, `building_id`, `building_name`, `total_flats`, `maintenance`, `description`, `is_active`, `added_on`, `added_by`, `modified_on`, `modified_by`, `deleted_on`, `deleted_by`) VALUES
(1, 'f189ab57f4fe838b4713187afac2fc99', 'A', 20, '10000.00', '', 1, '2020-12-12 10:29:56', 1, '2020-12-12 19:46:49', 1, NULL, NULL),
(2, '5e6c095d9a3d5cdb802ae79d8350e079', 'B', 20, '8500.00', 'Test', 1, '2020-12-12 10:30:31', 1, '2020-12-19 11:44:54', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_category`
--

CREATE TABLE `tbl_category` (
  `z_categoryid_pk` int(11) NOT NULL,
  `categoryid` varchar(255) NOT NULL DEFAULT '',
  `category_name` varchar(255) NOT NULL DEFAULT '',
  `cat_type` int(11) NOT NULL DEFAULT '0',
  `description` varchar(255) NOT NULL,
  `category_img` varchar(255) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT '1' COMMENT '1=active,0=inctive',
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) NOT NULL DEFAULT '0',
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) NOT NULL DEFAULT '0',
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_category`
--

INSERT INTO `tbl_category` (`z_categoryid_pk`, `categoryid`, `category_name`, `cat_type`, `description`, `category_img`, `is_active`, `added_on`, `added_by`, `modified_on`, `modified_by`, `deleted_on`, `deleted_by`) VALUES
(1, 'aa4802d18f17eb715fb226e49fffbf77', 'Maintenance', 2, 'Maintenance', NULL, 1, '2020-12-12 20:18:19', 1, '2020-12-19 12:00:50', 1, NULL, 0),
(2, 'ba715d4895fa6a0057ab6d1552db5d21', 'Water Bill', 1, 'Water Bill', NULL, 1, '2020-12-12 20:19:40', 1, '2020-12-19 12:00:46', 1, NULL, 0),
(3, 'ac471e5f589c33ad79e0cf04582be817', 'Electricity bill', 1, 'Electricity bill', NULL, 1, '2020-12-12 20:19:48', 1, '2020-12-19 12:00:42', 1, NULL, 0),
(4, '0c006d024a3bef305891da419f715702', 'Test', 3, 'Testr', NULL, 1, '2020-12-19 12:01:04', 1, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_flat_holders`
--

CREATE TABLE `tbl_flat_holders` (
  `z_fholderid_pk` int(11) NOT NULL,
  `fholder_id` varchar(255) NOT NULL,
  `building_id` varchar(255) NOT NULL,
  `flat_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile_no` varchar(100) NOT NULL,
  `email_id` varchar(255) NOT NULL,
  `flat_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1=Owner,2=Rent',
  `owner_name` varchar(100) NOT NULL,
  `owner_mobile_no` varchar(100) NOT NULL,
  `owner_email` varchar(255) NOT NULL,
  `owner_address` varchar(255) NOT NULL,
  `rent_aggrement` tinyint(4) NOT NULL DEFAULT '2' COMMENT '1=Yes,2=No',
  `is_president` tinyint(1) NOT NULL DEFAULT '2' COMMENT '1=Yes,2=No',
  `is_credentials_send` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=No.1=Yes',
  `aggrement_start_date` date DEFAULT NULL,
  `aggrement_end_date` date DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1=Yes,0=No',
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_flat_holders`
--

INSERT INTO `tbl_flat_holders` (`z_fholderid_pk`, `fholder_id`, `building_id`, `flat_no`, `name`, `mobile_no`, `email_id`, `flat_type`, `owner_name`, `owner_mobile_no`, `owner_email`, `owner_address`, `rent_aggrement`, `is_president`, `is_credentials_send`, `aggrement_start_date`, `aggrement_end_date`, `is_active`, `added_on`, `added_by`, `modified_on`, `modified_by`, `deleted_on`, `deleted_by`) VALUES
(1, 'da89766a357ac547f911c3970a113a92', 'f189ab57f4fe838b4713187afac2fc99', '101', 'Jigar', '8733051014', 'jigarsurati123@gmail.com', 1, '', '', '', '', 0, 1, 1, NULL, NULL, 1, '2020-12-12 14:41:52', 1, '2021-02-14 08:35:57', 1, NULL, NULL),
(2, '9deaf5630dfcde0acb9e2b5f46fc56c6', 'f189ab57f4fe838b4713187afac2fc99', '202', 'Surati', '9876543210', 'jigarsurati111@gmail.com', 2, 'Jigar', '9863258741', 'jigarsurati123@gmail.com', 'Surat', 1, 2, 1, '2019-01-02', '2020-12-31', 1, '2020-12-12 14:44:14', 1, '2021-02-13 11:19:47', 3, NULL, NULL),
(3, '5917b25affde6a2b57fb23b743378245', '5e6c095d9a3d5cdb802ae79d8350e079', '101', 'Test 1', '9865320236', 'jigarsurati2@gmail.com', 2, 'test', '7896541230', 'test1@gmail.com', 'surat', 2, 2, 0, NULL, NULL, 1, '2020-12-12 14:45:33', 1, '2021-02-13 11:23:25', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment`
--

CREATE TABLE `tbl_payment` (
  `z_paymentid_pk` int(11) NOT NULL,
  `payment_id` varchar(255) NOT NULL,
  `pay_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1=Dr,2=Cr',
  `category_id` varchar(255) NOT NULL,
  `building_id` varchar(255) DEFAULT NULL,
  `flat_holder_id` varchar(255) DEFAULT NULL,
  `pay_amount` decimal(10,2) NOT NULL,
  `pay_date` date NOT NULL,
  `short_desc` text NOT NULL,
  `pay_document` varchar(100) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_payment`
--

INSERT INTO `tbl_payment` (`z_paymentid_pk`, `payment_id`, `pay_type`, `category_id`, `building_id`, `flat_holder_id`, `pay_amount`, `pay_date`, `short_desc`, `pay_document`, `added_on`, `added_by`, `modified_on`, `modified_by`, `deleted_on`, `deleted_by`) VALUES
(1, '74484b7d5f76051e6d451b2ab5fc66db', 1, 'ac471e5f589c33ad79e0cf04582be817', '5e6c095d9a3d5cdb802ae79d8350e079', '0', '2000.00', '2020-12-19', 'this is test', 'http://127.0.0.1:8000/uploads/documents/cr/20201219202035SRSRT15873610000010112_new.pdf', '2020-12-19 20:20:35', 1, NULL, NULL, NULL, NULL),
(2, '6db2c2c38d2ca29eb6112ab9ef841587', 2, 'aa4802d18f17eb715fb226e49fffbf77', 'f189ab57f4fe838b4713187afac2fc99', 'da89766a357ac547f911c3970a113a92', '1000.00', '2020-12-19', 'This is yest', '', '2020-12-19 20:27:46', 1, NULL, NULL, NULL, NULL),
(3, '8696ea3910efbbf56c4493535ef42d9c', 1, 'ac471e5f589c33ad79e0cf04582be817', 'f189ab57f4fe838b4713187afac2fc99', '0', '100.00', '2021-01-25', 'Electicity bill', 'http://localhost:8000/uploads/documents/cr/20210131093139photo-1546146830-2cca9512c68e.jpeg', '2021-01-31 09:31:39', 1, NULL, NULL, NULL, NULL),
(4, '3f0ad559f83af091282813c45784ade8', 2, 'aa4802d18f17eb715fb226e49fffbf77', '5e6c095d9a3d5cdb802ae79d8350e079', '5917b25affde6a2b57fb23b743378245', '10000.00', '2020-08-01', 'Test', '', '2021-01-31 09:34:11', 1, NULL, NULL, NULL, NULL),
(5, '8b3dbfe142a4934e210069c3303355cd', 1, 'ac471e5f589c33ad79e0cf04582be817', 'f189ab57f4fe838b4713187afac2fc99', '0', '100.00', '2021-01-30', 'Test', '', '2021-01-31 09:40:58', 1, NULL, NULL, NULL, NULL),
(6, 'ce18142384f93518a6d9645c6c400f6d', 1, 'ba715d4895fa6a0057ab6d1552db5d21', 'f189ab57f4fe838b4713187afac2fc99', '0', '100.00', '2021-02-14', 'Test', '', '2021-02-14 16:35:16', 5, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_auth`
--
ALTER TABLE `admin_auth`
  ADD PRIMARY KEY (`z_authid_pk`);

--
-- Indexes for table `admin_log`
--
ALTER TABLE `admin_log`
  ADD PRIMARY KEY (`z_logid_pk`);

--
-- Indexes for table `admin_mst`
--
ALTER TABLE `admin_mst`
  ADD PRIMARY KEY (`z_adminid_pk`);

--
-- Indexes for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  ADD PRIMARY KEY (`z_buildingid_pk`);

--
-- Indexes for table `tbl_category`
--
ALTER TABLE `tbl_category`
  ADD PRIMARY KEY (`z_categoryid_pk`);

--
-- Indexes for table `tbl_flat_holders`
--
ALTER TABLE `tbl_flat_holders`
  ADD PRIMARY KEY (`z_fholderid_pk`);

--
-- Indexes for table `tbl_payment`
--
ALTER TABLE `tbl_payment`
  ADD PRIMARY KEY (`z_paymentid_pk`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_auth`
--
ALTER TABLE `admin_auth`
  MODIFY `z_authid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT for table `admin_log`
--
ALTER TABLE `admin_log`
  MODIFY `z_logid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT for table `admin_mst`
--
ALTER TABLE `admin_mst`
  MODIFY `z_adminid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  MODIFY `z_buildingid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `tbl_category`
--
ALTER TABLE `tbl_category`
  MODIFY `z_categoryid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `tbl_flat_holders`
--
ALTER TABLE `tbl_flat_holders`
  MODIFY `z_fholderid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `tbl_payment`
--
ALTER TABLE `tbl_payment`
  MODIFY `z_paymentid_pk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
