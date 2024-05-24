
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- tapestry implementation : © Adam Dewbery <adam@dewbs.co.uk>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';


CREATE TABLE IF NOT EXISTS `playerextra` (
 `player_id` int(10) unsigned NOT NULL COMMENT 'Reference to metagame player id',
 `player_name` varchar(32) NOT NULL,
 `player_avatar` varchar(10) NOT NULL,
 `player_color` varchar(6) NOT NULL,
 `player_score` int(10) NOT NULL DEFAULT '0',
 `player_score_aux` int(10) NOT NULL DEFAULT '0',
 `player_ai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = player is an AI',
 `player_track_technology` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_track_science` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_track_military` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_track_exploration` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_res_food` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_res_coin` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_res_culture` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_res_worker` INT UNSIGNED NOT NULL DEFAULT '0',
 `player_income_farms` INT UNSIGNED NOT NULL DEFAULT '1',
 `player_income_armories` INT UNSIGNED NOT NULL DEFAULT '1',
 `player_income_houses` INT UNSIGNED NOT NULL DEFAULT '1',
 `player_income_markets` INT UNSIGNED NOT NULL DEFAULT '1',
 `player_income_turns` INT UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `card` (
   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `card_type` varchar(16) NOT NULL,
   `card_type_arg` int(11) NOT NULL,
   `card_location` varchar(16) NOT NULL,
   `card_location_arg` int(11) NOT NULL,
   `card_location_arg2` varchar(16) NOT NULL DEFAULT '0',
   PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `structure` (
   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `card_type` varchar(16) NOT NULL,
   `card_type_arg` int(11) NOT NULL,
   `card_location` varchar(50) NOT NULL,
   `card_location_arg` int(11) NOT NULL  DEFAULT 0,
   `card_location_arg2` varchar(16) NOT NULL DEFAULT '0',
   PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `benefit` (
   `benefit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `benefit_category` varchar(64) NOT NULL,
   `benefit_type` int(11) NOT NULL,
   `benefit_prerequisite` int(10),
   `benefit_quantity` int(10) NOT NULL DEFAULT 1,
   `benefit_data` varchar(64),
   `benefit_player_id` int(11) NOT NULL,
   PRIMARY KEY (`benefit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `map` (
   `map_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `map_coords` varchar(16) NOT NULL,
   `map_owner` int(11),
   `map_tile_id` int(4) NOT NULL DEFAULT 0,
   `map_tile_orient` int(4) NOT NULL DEFAULT 0,
   PRIMARY KEY (`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `capital` (
   `capital_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
   `player_id` int(11) NOT NULL,
   `capital_x` int(11) NOT NULL,
   `capital_y` int(11) NOT NULL,
   `capital_occupied` int(2) NOT NULL DEFAULT 0,
   PRIMARY KEY (`capital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;