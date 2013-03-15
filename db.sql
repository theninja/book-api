# Backticks not necessary as table and column identifiers strictly use [0-9,a-z,A-Z$_]

CREATE DATABASE IF NOT EXISTS ebookstore
DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_bin;
SET storage_engine=InnoDB;
USE ebookstore;

-- -----------------------------------------------------
-- Table ebookstore.books
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
	id          int UNSIGNED NOT NULL AUTO_INCREMENT,
	title       varchar(200) NOT NULL,
	author      varchar(60) NOT NULL,
	price       decimal(19,2) UNSIGNED NULL,
	description text NULL,
	image       varchar(200),
	content     varchar(200),
	PRIMARY KEY (id)
);


-- -----------------------------------------------------
-- Table ebookstore.users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
	id          int UNSIGNED NOT NULL AUTO_INCREMENT,
	username    varchar(40) NOT NULL,
	email       varchar(254) NULL,
	password    varchar(60) NULL,
	is_admin    TINYINT(1) NULL DEFAULT 0,
	create_time timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY username (username)
);


-- -----------------------------------------------------
-- Table ebookstore.authors
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS authors (
	id        int UNSIGNED NOT NULL AUTO_INCREMENT,
	firstname varchar(45) NOT NULL,
	lastname  varchar(45) NOT NULL,
	 PRIMARY KEY (id)
);


-- -----------------------------------------------------
-- Table ebookstore.authors_books
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS authors_books (
	book_id   int UNSIGNED NOT NULL,
	author_id int UNSIGNED NOT NULL,
	PRIMARY KEY (book_id,author_id),
	INDEX fk_authors_map_books_idx (book_id),
	INDEX fk_authors_map_authors_idx (author_id),
	CONSTRAINT fk_authors_map_books
		FOREIGN KEY (book_id)
		REFERENCES books (id),
	CONSTRAINT fk_authors_map_authors
		FOREIGN KEY (author_id)
		REFERENCES authors (id)
);


-- -----------------------------------------------------
-- Table ebookstore.purchases
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
	id        int UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id   int UNSIGNED NOT NULL,
	book_id   int UNSIGNED NOT NULL,
	downloads tinyint UNSIGNED NOT NULL,
	date      datetime NULL,
	status    varchar(45) NULL,
	PRIMARY KEY (id),
	INDEX fk_purchases_users_idx (user_id),
	INDEX fk_purchases_books_idx (book_id),
	CONSTRAINT fk_purchases_users
		FOREIGN KEY (user_id)
		REFERENCES users (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION,
	CONSTRAINT fk_purchases_books
		FOREIGN KEY (book_id)
		REFERENCES books (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION
);


-- -----------------------------------------------------
-- Table ebookstore.reviews
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
	id      int UNSIGNED NOT NULL AUTO_INCREMENT,
	book_id int UNSIGNED NULL,
	user_id int UNSIGNED NULL,
	rating  decimal(2,1) UNSIGNED NULL,
	review  text NULL,
	PRIMARY KEY (id),
	INDEX fk_reviews_books_idx (book_id),
	INDEX fk_reviews_users_idx (user_id),
	CONSTRAINT fk_reviews_books
		FOREIGN KEY (book_id)
		REFERENCES books (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION,
	CONSTRAINT fk_reviews_users
		FOREIGN KEY (user_id)
		REFERENCES users (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION
);



--------------------------------------------------------
-- Auditing not implemented yet
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Table ebookstore.logtype
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS logtype (
	id   int UNSIGNED NOT NULL AUTO_INCREMENT,
	type varchar(45) NOT NULL,
	PRIMARY KEY (id)
);


-- -----------------------------------------------------
-- Table ebookstore.audits
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS audits (
	id         int UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id    int UNSIGNED NOT NULL,
	logtype_id int UNSIGNED NOT NULL,
	hash       varchar(40) NOT NULL,
	signature  varchar(255) NOT NULL,
	time       timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	INDEX fk_audits_users_idx (user_id),
	INDEX fk_audits_logtype_idx (logtype_id),
	CONSTRAINT fk_audits_users
		FOREIGN KEY (user_id)
		REFERENCES users (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION,
	CONSTRAINT fk_audits_logtype
		FOREIGN KEY (logtype_id)
		REFERENCES logtype (id)
		ON DELETE NO ACTION
		ON UPDATE NO ACTION
);

CREATE TRIGGER stop_updates BEFORE UPDATE ON audits
FOR EACH ROW
	SIGNAL SQLSTATE '45000'
	SET MESSAGE_TEXT = 'Updating logs is not allowed';

CREATE TRIGGER stop_deletes BEFORE DELETE ON audits
FOR EACH ROW
	SIGNAL SQLSTATE '45000'
	SET MESSAGE_TEXT = 'Deleting logs is not allowed';