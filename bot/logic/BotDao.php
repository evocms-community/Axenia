<?php

class BotDao extends AbstractDao
{

    // --- Users

    public function getUserID($username)
    {
        $username = "'" . (isset($username) ? $this->escape_mimic($username) : '') . "'";
        $username = strtolower(str_ireplace("@", "", $username));

        $res = $this->select("SELECT id FROM users WHERE LOWER(username)=$username");

        return (!$res[0]) ? false : $res[0];
    }

    public function insertOrUpdateUser($user)
    {
        $user_id = $user['id'];

        $username = Util::wrapQuotes(isset($user['username']) ? $this->escape_mimic($user['username']) : '');
        $firstname = Util::wrapQuotes(isset($user['first_name']) ? $this->escape_mimic($user['first_name']) : '');
        $lastname = Util::wrapQuotes(isset($user['last_name']) ? $this->escape_mimic($user['last_name']) : '');

        $query = "
            INSERT INTO users (id, username, firstname, lastname, date_added)
            VALUES ($user_id, $username, $firstname, $lastname, NOW())
            ON DUPLICATE KEY UPDATE username=$username, firstname=$firstname, lastname=$lastname, last_updated=NOW()
        ";

        return $this->insert($query);
    }

    public function getUser($id)
    {
        $res = $this->select("SELECT username, firstname, lastname FROM users WHERE id=" . $id);

        return (!$res[0] && !$res[1] && !$res[2]) ? false : [
            'username' => $res[0],
            'first_name' => $res[1],
            'last_name' => $res[2],
        ];
    }

    public function getUsersIDByUsername($username)
    {
        $username = "'" . (isset($username) ? $this->escape_mimic($username) : '') . "'";
        $username = strtolower(str_ireplace("@", "", $username));

        $res = $this->select("SELECT u.id, k.chat_id FROM users u, karma k WHERE k.user_id=u.id AND LOWER(u.username)=" . $username);

        return (!$res[0]) ? false : $res;
    }

    public function getUserName($id)
    {
        $res = $this->select("SELECT username, firstname, lastname FROM users WHERE id=" . $id);

        return (!$res[0]) ? $res[1] : $res[0];
    }

    public function getUsersByName($query, $limit = 0)
    {
        if ($query != '') {
            $query = "'" . strtolower("%" . $query . "%") . "'";

            $res = $this->select(
                "SELECT id, firstname, lastname, username
                  FROM users
                  WHERE CONCAT(username, firstname, lastname) LIKE $query LIMIT " . $limit
            );

            return $res;
        }

        return false;
    }

    public function isHidden($user_id)
    {
        $res = $this->select("SELECT hidden FROM users WHERE id=" . $user_id);

        return (is_null($res[0])) ? null : $res[0];
    }

    public function setHidden($user_id, $value)
    {
        return $this->update("UPDATE users SET hidden = " . $value . " WHERE id = " . $user_id);
    }



    // --- Lang

    public function getChatLang($chat_id)
    {
        $res = $this->select("SELECT lang FROM chats WHERE id = " . $chat_id, true);

        if (isset($res[0])) {
            return !($res[0]) ? false : $res[0];
        }

        return false;
    }

    public function getSilentMode($chat_id)
    {
        $res = $this->select("SELECT silent_mode FROM chats WHERE id = " . $chat_id, true);

        if (isset($res[0])) {
            return ($res[0] == 1) ? true : false;
        }

        return false;
    }

    public function setSilentMode($chat_id, $mode)
    {
        return $this->update("UPDATE chats SET silent_mode = " . (($mode) ? 1 : 0) . " WHERE id=" . $chat_id);
    }

    public function getUserLang($user_id)
    {
        $res = $this->select("SELECT lang FROM users WHERE id = " . $user_id);

        return !($res[0]) ? false : $res[0];
    }

    public function setChatLang($chat_id, $lang)
    {
        return $this->update("UPDATE chats SET lang = '" . $lang . "' WHERE id = " . $chat_id);
    }

    public function setUserLang($user_id, $lang)
    {
        return $this->update("UPDATE users SET lang = '" . $lang . "' WHERE id = " . $user_id);
    }



    // --- Chats

    public function getChatsIds()
    {
        $res = $this->select("SELECT id FROM chats");

        return (!$res) ? false : $res;
    }

    public function getCooldown($chat_id)
    {
        $res = $this->select("SELECT cooldown FROM chats WHERE id=" . $chat_id);

        return ($res[0] === null) ? DEFAULT_COOLDOWN : $res[0];
    }

    public function getGrowth($chat_id)
    {
        $res = $this->select("SELECT ariphmeticGrowth FROM chats WHERE id=" . $chat_id);

        return ($res[0] === null) ? 0 : $res[0];
    }

    public function getAccess($chat_id)
    {
        $res = $this->select("SELECT forAdmin FROM chats WHERE id=" . $chat_id);

        return ($res[0] === null) ? 0 : $res[0];
    }

    public function setGrowth($chat_id, $value)
    {
        return $this->update("UPDATE chats SET ariphmeticGrowth = " . $value . " WHERE id = " . $chat_id);
    }

    public function setAccess($chat_id, $value)
    {
        return $this->update("UPDATE chats SET forAdmin = " . $value . " WHERE id = " . $chat_id);
    }

    public function setCooldown($chat_id, $cooldown)
    {
        return $this->update("UPDATE chats SET cooldown = " . $cooldown . " WHERE id = " . $chat_id);
    }

    public function insertOrUpdateChat($chat)
    {
        $chat_id = $chat['id'];

        $username = Util::wrapQuotes(isset($chat['username']) ? $this->escape_mimic($chat['username']) : '');
        $title = Util::wrapQuotes(isset($chat['title']) ? $this->escape_mimic($chat['title']) : '');

        $query = "
            INSERT INTO chats(id, title, username, date_add, date_remove)
            VALUES($chat_id, $title, $username, NOW(), null)
            ON DUPLICATE KEY UPDATE title=$title, username=$username, date_remove=null
        ";

        return $this->insert($query);
    }

    public function deleteChat($chat_id)
    {
        $query = "DELETE FROM chats WHERE id = " . $chat_id;

        return $this->delete($query);
    }

    public function getUserGroups($user_id)
    {
        $res = $this->select("
            SELECT c.title, c.username, c.id
            FROM chats c, karma k
            WHERE k.chat_id=c.id AND k.user_id=" . $user_id . " and c.isPresented=1
            ORDER BY c.title
        ");

        return (!$res[2]) ? false : $res;
    }

    public function getMembersCount($chat_id)
    {
        $res = $this->select("
            SELECT count(1)
            FROM karma k
            WHERE k.chat_id=" . $chat_id . "
        ");

        return (!$res[0]) ? 0 : $res[0];
    }

    public function getGroupName($chat_id)
    {
        $res = $this->select("SELECT title FROM chats WHERE id = '" . $chat_id . "'");

        return (!$res[0]) ? false : htmlspecialchars($res[0]);
    }

    public function getGroupsByName($query, $limit = 0)
    {
        if ($query != '') {
            $query = "'" . strtolower("%" . $query . "%") . "'";

            $res = $this->select("
                SELECT id, title
                FROM chats
                WHERE title LIKE $query LIMIT " . $limit . "
            ");

            return $res;
        }

        return false;
    }

    public function getGroupsMistakes()
    {
        $res = $this->select(
            "SELECT DISTINCT k.chat_id
            FROM karma k
            WHERE NOT(k.last_updated IS null) AND k.chat_id NOT IN (SELECT id FROM chats)"
        );

        $temp = $this->select(
            "SELECT DISTINCT k.chat_id, c.title
            FROM chats c, karma k
            WHERE NOT(k.last_updated IS null) AND k.chat_id=c.id AND (c.title='')"
        );

        $res = array_merge($res, $temp);

        return (!$res) ? false : $res;
    }

    public function setPresented($chat_id, $isPresented)
    {
        $res = $this->update("
            UPDATE chats
            SET isPresented = " . (($isPresented) ? 1 : 0) . ", date_remove = " . (($isPresented) ? "null" : "now()") . "
            WHERE id=" . $chat_id . "
        ");

        return $res;
    }

    public function changeChatIdIn($newChatId, $oldChatId)
    {
        $query = "UPDATE chats SET id=" . $newChatId . " WHERE id = " . $oldChatId;

        return $this->update($query);
    }



    //--- Karma

    public function getTop($chat_id, $limit = 5)
    {
        $query = "
        SELECT u.username, u.firstname, u.lastname, k.level
        FROM karma AS k, users AS u
        WHERE k.user_id=u.id AND k.level<>0 AND k.chat_id='" . $chat_id . "'
        ORDER BY level
        DESC LIMIT " . $limit;

        return $this->select($query);
    }

    /**
     * получить уровень кармы пользователя из чата
     * @param $user_id
     * @param $chat_id
     * @return mixed
     */
    public function getUserLevel($user_id, $chat_id)
    {
        $query = "SELECT level FROM karma WHERE user_id=" . $user_id . " AND chat_id=" . $chat_id;
        $res = $this->select($query);

        return $res;
    }

    /**
     * Добавляет запись с уровня кармы пользователя в чате.
     * Если пользователь уже имеется с каким то левелом то левел обновится из параметра $level
     * @param $user_id
     * @param $chat_id
     * @param $level
     * @return mixed
     */
    public function setUserLevel($user_id, $chat_id, $level)
    {
        $user_id = $this->clearForInsert($user_id);
        $chat_id = $this->clearForInsert($chat_id);
        $level = $this->clearForInsert($level);

        $query = "
            INSERT INTO karma (user_id, chat_id, level)
            VALUES ($user_id, $chat_id, $level)
            ON DUPLICATE KEY UPDATE level = " . $level . ", last_updated=NOW()
        ";

        return $this->insert($query);
    }

    public function setLastTimeVote($from_id, $chat_id)
    {
        $query = "
            UPDATE karma SET last_time_voted=now(), toofast_showed=0
            WHERE user_id=" . $from_id . " and chat_id=" . $chat_id;

        return $this->update($query);
    }

    public function setTooFastShowed($from_id, $chat_id)
    {
        $query = "
            UPDATE karma set toofast_showed=1
            WHERE user_id=" . $from_id . " and chat_id=" . $chat_id;

        return $this->update($query);
    }

    public function getTooFastShowed($from_id, $chat_id)
    {
        $res = $this->select("
            SELECT toofast_showed
            FROM Karma
            WHERE user_id=" . $from_id . " AND chat_id=" . $chat_id . "
        ");

        return ($res[0] == null) ? false : $res[0];
    }

    public function checkCooldown($from_id, $chat_id)
    {
        $res = $this->select("SELECT NOW()-last_time_voted from karma
            WHERE user_id=" . $from_id . " AND chat_id=" . $chat_id);

        return (!$res[0]) ? false : $res[0];
    }

    public function isCooldown($from_id, $chat_id)
    {
        $res = $this->select("
            SELECT IF(last_time_voted IS NOT null, ((NOW()- last_time_voted) < (cooldown*60)), 0) isCoolDown
            FROM karma
            LEFT JOIN chats ON karma.chat_id = chats.id
            WHERE user_id=" . $from_id . " AND chat_id=" . $chat_id . "
        ");

        return (count($res) == 0 || $res[0] == null) ? false : $res[0];
    }

    public function sumKarma($user_id)
    {
        $res = $this->select("SELECT SUM(level) FROM karma WHERE user_id=" . $user_id);

        return (!$res[0]) ? 0 : $res[0];
    }

    public function usersPlace($user_id)
    {
        $res = $this->select(
            "SELECT COUNT(a.Sumlevel)
              FROM
              (   SELECT SUM(level) AS SumLevel
                  FROM karma k
                  GROUP BY k.user_id) a,
              (   SELECT SUM(level) AS SumLevel
                  FROM karma
                  WHERE user_id=" . $user_id . ") u
                WHERE u.SumLevel<=a.SumLevel
                ORDER BY a.SumLevel ASC;"
        );

        return (!$res[0]) ? false : $res[0];
    }

    public function getAllKarmaPair()
    {
        $res = $this->select("SELECT k.user_id, k.chat_id FROM karma k");

        return (!$res[0]) ? false : $res;
    }

    public function deleteUserKarmaInChat($userId, $chatId)
    {
        $query = "DELETE FROM karma WHERE user_id = " . $userId . " AND chat_id = " . $chatId;

        return $this->delete($query);
    }

    public function deleteAllKarmaInChat($chatId)
    {
        $query = "DELETE FROM karma WHERE chat_id = " . $chatId;

        return $this->delete($query);
    }

    public function changeChatIdInKarma($newChatId, $oldChatId)
    {
        $query = "UPDATE karma SET chat_id=" . $newChatId . " WHERE chat_id = " . $oldChatId;

        return $this->update($query);
    }
}
