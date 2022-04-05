-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Хост: 10.0.0.86
-- Время создания: Апр 05 2022 г., 23:23
-- Версия сервера: 5.7.35-38
-- Версия PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `bot`
--

-- --------------------------------------------------------

--
-- Структура таблицы `chats`
--

CREATE TABLE `chats` (
  `id` bigint(32) NOT NULL,
  `title` varchar(256) NOT NULL,
  `username` varchar(128) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_remove` datetime DEFAULT NULL,
  `lang` varchar(8) NOT NULL DEFAULT 'en',
  `silent_mode` tinyint(1) NOT NULL DEFAULT '0',
  `cooldown` float UNSIGNED NOT NULL,
  `ariphmeticGrowth` tinyint(1) NOT NULL DEFAULT '1',
  `forAdmin` int(11) NOT NULL,
  `isPresented` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Структура таблицы `karma`
--

CREATE TABLE `karma` (
  `chat_id` bigint(32) NOT NULL,
  `user_id` bigint(32) NOT NULL,
  `level` float NOT NULL DEFAULT '0',
  `last_updated` datetime NOT NULL,
  `last_time_voted` datetime NOT NULL,
  `toofast_showed` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` bigint(32) NOT NULL,
  `username` varchar(128) NOT NULL,
  `firstname` varchar(256) NOT NULL,
  `lastname` varchar(256) NOT NULL,
  `date_added` datetime NOT NULL,
  `last_updated` datetime NOT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `lang` varchar(8) NOT NULL DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `title` (`title`);

--
-- Индексы таблицы `karma`
--
ALTER TABLE `karma`
  ADD UNIQUE KEY `uniq` (`chat_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `last_updated` (`last_updated`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
