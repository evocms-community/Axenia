<?php

class Axenia
{
    private $service;

    /**
     * Axenia constructor.
     * @param $service BotService
     */
    public function __construct($service)
    {
        $this->service = $service;
    }

    public function handleUpdate($update)
    {
        if (isset($update["message"]) || isset($update["inline_query"]) || isset($update["callback_query"]) || isset($update["pre_checkout_query"])) {
            // debug
            if (DEBUG_LOG) {
                file_put_contents('log.txt', print_r($update, true)."\n---\n", FILE_APPEND);
            }

            // проверка разрешённого чата
            if (defined('ONLY_CHATS') && !empty(ONLY_CHATS) && in_array($update['message']['chat']['id'], ONLY_CHATS) == false) {
                Request::sendMessage($update['message']['chat']['id'], Lang::message('chat.notallowed'), [ "parse_mode" => "Markdown" ]);
                return false;
            }

            try {
                if (isset($update["message"])) {
                    $this->processMessage($update["message"]);
                } elseif (isset($update["inline_query"])) {
                    $this->processInline($update["inline_query"]);
                } elseif (isset($update["callback_query"])) {
                    $this->processCallback($update["callback_query"]);
                }
            } catch (Exception $e) {
                print_r("Boterror!");
                $this->service->handleException($e, $update);
            }
        }
    }

    /**
     * Check if is need to handle the message by bot
     * @param $message
     * @return bool
     */
    private function needToHandle($message)
    {
        if ($message['chat']['type'] != "channel") {
            if (isset($message['text'])) {
                return Util::startsWith($message['text'], ["/", "＋", "+", "-", '👍', '👎']);
            }
            if (isset($message['sticker'])) {
                return Util::startsWith($message['sticker']['emoji'], ['👍', '👎']);
            }
            if (isset($message['new_chat_member']) || isset($message['new_chat_title']) || isset($message['left_chat_member']) || isset($message['migrate_to_chat_id'])) {
                return true;
            }
        }

        return false;
    }

    public function processMessage($message)
    {
        if ($this->needToHandle($message)) {
            $message_id = $message['message_id'];
            $chat = $message['chat'];
            $from = $message['from'];

            $chat_id = $chat['id'];
            $from_id = $from['id'];

            $this->service->insertOrUpdateUser($from);
            $isNewChat = $this->service->initLang($chat);
            if ($isNewChat) {
                $this->service->rememberChat($chat, $from_id);
            }

            if (isset($message['text']) || isset($message['sticker'])) {
                $isPrivate = $this->service->isPrivate($chat);
                $postfix = $isPrivate ? "" : ("@" . BOT_NAME);

                if (isset($message['sticker'])) {
                    $text = $message['sticker']['emoji'];
                } else {
                    $text = $message['text'];
                }

                switch (true) {
                    // изменить карму
                    case Util::startsWith($text, ["＋", "+", "-", '👍', '👎']):
                        if ($isPrivate) {
                            Request::sendMessage($chat_id, Lang::message("bot.onlyPrivate"));
                        } else {
                            if (preg_match('/^(\+|\-|👍|👎|＋) ?([\s\S]+)?/ui', $text, $matches)) {
                                if ($this->service->checkConditions($from_id, $chat)) {
                                    $isRise = Util::isInEnum("＋,+,👍", $matches[1]);
                                    if (isset($message['reply_to_message'])) {
                                        $replyUser = $message['reply_to_message']['from'];
                                        if ($replyUser['username'] != BOT_NAME && !$this->service->isUserBot($replyUser)) {
                                            $this->service->insertOrUpdateUser($replyUser);
                                            $this->doKarmaAction($isRise, $from_id, $replyUser['id'], $chat_id);
                                        }
                                    } else {
                                        if (preg_match('/@([\w]+)/ui', $matches[2], $user)) {
                                            if (BOT_NAME != $user[1] && !$this->service->isUsernameEndBot($user[1])) {
                                                $to = $this->service->getUserID($user[1]);
                                                if ($to) {
                                                    if (Request::isChatMember($to, $chat_id)) {
                                                        $this->doKarmaAction($isRise, $from_id, $to, $chat_id);
                                                    } else {
                                                        Request::sendHtmlMessage($chat_id, Lang::message('karma.unknownUser.kicked'), ['reply_to_message_id' => $message_id]);
                                                    }
                                                } else {
                                                    Request::sendHtmlMessage($chat_id, Lang::message('karma.unknownUser'), ['reply_to_message_id' => $message_id]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    // настройки
                    case Util::startsWith($text, "/settings" . $postfix):
                        Request::sendTyping($chat_id);
                        $this->sendSettings($chat, null, null, $this->service->isAdminInChat($from_id, $chat));
                        break;

                    // топ кармы
                    case Util::startsWith($text, "/top" . $postfix):
                        Request::sendTyping($chat_id);
                        if ($isPrivate) {
                            Request::sendMessage($chat_id, Lang::message("bot.onlyPrivate"));
                        } else {
                            $out = $this->service->getTop($chat_id, 10);

                            Request::sendHtmlMessage($chat_id, $out);
                        }
                        break;

                    // топ антикармы
                    case Util::startsWith($text, "/antitop" . $postfix):
                        Request::sendTyping($chat_id);
                        if ($isPrivate) {
                            Request::sendMessage($chat_id, Lang::message("bot.onlyPrivate"));
                        } else {
                            $out = $this->service->getAntitop($chat_id, 10);

                            Request::sendHtmlMessage($chat_id, $out);
                        }
                        break;

                    // своя статистика
                    case Util::startsWith($text, "/mystats" . $postfix):
                        Request::sendTyping($chat_id);
                        $statsMessage = $this->service->getStats($from, $isPrivate ? null : $chat_id);
                        Request::sendHtmlMessage($chat_id, $statsMessage);
                        break;

                    // старт
                    case Util::startsWith($text, "/start" . $postfix):
                        if ($isPrivate) {
                            Request::sendTyping($chat_id);
                            Request::sendHtmlMessage($chat_id, Lang::message('chat.greetings'));
                            Request::sendHtmlMessage($chat_id, Lang::message('user.pickChat', ["botName" => BOT_NAME]));
                        } else {
                            $this->service->rememberChat($chat, $from_id);
                            Request::sendHtmlMessage($chat_id, Lang::message('bot.start'));
                        }
                        break;

                    // помощь
                    case Util::startsWith($text, "/help" . $postfix):
                        Request::sendHtmlMessage($chat_id, Lang::message('chat.help'));
                        break;

                    // set
                    case Util::startsWith($text, ("/set @")):
                        if ($this->service->checkRights($from_id, 5)) {
                            if (preg_match('/^(\/set) @([\w]+) (-?\d+)/ui ', $text, $matches)) {
                                Request::sendMessage($from_id, $this->service->setLevelByUsername($matches[2], $chat_id, $matches[3]));
                            }
                        }
                        break;
                }
            } elseif (isset($message['new_chat_member'])) {
                $newMember = $message['new_chat_member'];
                if (BOT_NAME == $newMember['username']) {
                    $isRemembered = $this->service->rememberChat($chat, $from_id);
                    $this->service->setBotPresentedInChat($chat_id, true);
                    if ($isRemembered !== false) {
                        if (defined('LOG_CHAT_ID')) {
                            Request::sendHtmlMessage(LOG_CHAT_ID, " 🌝 " . Request::getChatMembersCount($chat_id) . "|" . $this->service->getChatMembersCount($chat_id) . " (" . Util::getChatLink($chat) . ") by " . Util::getFullNameUser($from, false));
                        }
                        Request::sendMessage($chat_id, Lang::message('chat.greetings'), ["parse_mode" => "Markdown"]);
                    }
                }
                // убрал пока
                //else { $this->service->insertOrUpdateUser($newMember); }
            } elseif (isset($message['new_chat_title'])) {
                $this->service->rememberChat($chat, $from_id);
            } elseif (isset($message['left_chat_member'])) {
                $member = $message['left_chat_member'];
                if (BOT_NAME == $member['username']) {
                    //$isDeleted = $this->service->deleteChat($chat_id);
                    $this->service->setBotPresentedInChat($chat_id, false);
                    if (defined('LOG_CHAT_ID')) {
                        Request::sendHtmlMessage(LOG_CHAT_ID, " 🌚 -1|" . $this->service->getChatMembersCount($chat_id) . " (" . Util::getChatLink($chat) . ") by " . Util::getFullNameUser($from, false));
                    }
                }
            } elseif (isset($message['migrate_to_chat_id'])) {
                $rez = $this->service->migrateToNewChatId($message['migrate_to_chat_id'], $chat_id);
            }
        }
    }

    public function doKarmaAction($isRise, $from_id, $user_id, $chat_id)
    {
        $out = $this->service->handleKarma($isRise, $from_id, $user_id, $chat_id);

        if (!$this->service->isSilentMode($chat_id)) {
            Request::sendHtmlMessage($chat_id, $out['msg']);
        }
    }

    public function processInline($inline)
    {
        $id = $inline['id'];
        $query = $inline['query'];

        if (isset($query) && $query !== "") {
            $users = $this->service->getUserList($query);

            if ($users) {
                Request::answerInlineQuery($id, $users);
            } else {
                Request::answerInlineQuery($id, [
                    [
                        "type" => "article",
                        "id" => "0",
                        "title" => Lang::message('chat.greetings'),
                        "message_text" => Lang::message('chat.greetings'),
                    ],
                ]);
            }
        }
    }

    public function sendSettings($chat, $message = null, $type = null, $showButtons = true)
    {
        $chat_id = $chat['id'];

        switch ($type) {
            case "set_cooldown":
                $minuteText = Lang::message('settings.minute');
                $button_list = [
                    [
                        ['text' => "0.1" . $minuteText, 'callback_data' => 'set_0'],
                        ['text' => "0.5" . $minuteText, 'callback_data' => 'set_0.5'],
                        ['text' => "1" . $minuteText, 'callback_data' => 'set_1'],
                    ],
                    [
                        ['text' => "2" . $minuteText, 'callback_data' => 'set_2'],
                        ['text' => "10" . $minuteText, 'callback_data' => 'set_10'],
                        ['text' => "20" . $minuteText, 'callback_data' => 'set_20'],
                    ],
                    [['text' => Lang::message("settings.button.back"), 'callback_data' => "set_back"]],
                ];
                $text = Lang::message('settings.select.cooldown');
                break;

            case "set_lang":
                $ln = Lang::availableLangs();

                $i = 0;
                $button_list = [];
                $a = [];

                foreach ($ln as $k => $v) {
                    $i++;
                    array_push($a, ['text' => $v, 'callback_data' => $k]);
                    if ($i % 2 == 0) {
                        array_push($button_list, $a);
                        $a = [];
                    }
                }
                if (count($a) > 0) {
                    array_push($button_list, $a);
                }

                array_push($button_list, [['text' => Lang::message("settings.button.back"), 'callback_data' => "set_back"]]);

                $text = Lang::message('settings.select.lang');
                break;

            case "set_eraseGroup":
                $user_id = $chat_id;

                $a = $this->service->getUserGroup($user_id, false);
                $buttons = [];
                foreach ($a as $item) {
                    $chat_id = explode(":", $item)[0];
                    $member = Request::getChatMember($user_id, $chat_id);
                    if ($member['status'] == "creator" || $member['status'] == "administrator") {
                        array_push($buttons, ['text' => explode(":", $item)[1], 'callback_data' => "erase_" . $chat_id]);
                    }
                }
                if (count($buttons) > 0) {
                    $button_list = array_chunk($buttons, 3);
                    $text = Lang::message('settings.erase.title') . "\r\n\r\n" . Lang::message('settings.groups.adminonly');
                } else {
                    $button_list = [];
                    $text = Lang::message('settings.erase.notallow');
                }

                $chat_id = $user_id;
                break;

            case "set_switchHidden":
                $this->service->toggleHidden($chat_id);
                $data = null;
                $this->sendSettings($chat, $message, $data);
                break;

            default:
                $text = ($this->service->isPrivate($chat)) ? 'settings.titlePrivate' : 'settings.titleGroup';
                $text = Lang::message($text) . "\r\n";
                if ($this->service->isPrivate($chat)) {
                    $button_list = [
                        [
                            [
                                'text' => Lang::message('settings.button.lang'),
                                'callback_data' => 'set_lang',
                            ],
                        ], [
                            [
                                'text' => Lang::message('settings.erase'),
                                'callback_data' => 'set_eraseGroup',
                            ],
                        ],
                    ];
                    $newButton = 'settings.hidden.';
                    $newButton .= $this->service->isHidden($chat_id) ? 'turnoff' : 'turnon';
                    $newButton = Lang::message($newButton);

                    array_push($button_list, [
                        [
                            'text' => $newButton,
                            'callback_data' => 'set_switchHidden',
                        ],
                    ]);
                    $text .= Lang::message("settings.title.lang", ["lang" => Lang::getCurrentLangDesc()]) . "\r\n";
                } else {
                    $button_list = [
                        [
                            ['text' => Lang::message("settings.button.toggle_silent_mode"),
                                'callback_data' => 'set_toggle_silent_mode',
                            ],
                            ['text' => Lang::message('settings.button.lang'),
                                'callback_data' => 'set_lang',
                            ],
                        ],
                        [['text' => Lang::message('settings.button.set_cooldown'),
                            'callback_data' => 'set_cooldown',
                        ]],
                        [['text' => Lang::message('settings.button.set_another_growth', ["type" => ($this->service->getGrowth($chat_id) == 0) ? Lang::message('settings.growth.ariphmetic') : Lang::message('settings.growth.geometric')]),
                            'callback_data' => 'set_another_growth',
                        ]],
                        [['text' => Lang::message('settings.button.set_another_access', ["type" => ($this->service->getAccess($chat_id) == 0) ? Lang::message('settings.access.for_admin') : Lang::message('settings.access.for_everyone')]),
                            'callback_data' => 'set_another_access',
                        ]],
                    ];

                    $text .= Lang::message("settings.title.silent_mode", ["status" => ($this->service->isSilentMode($chat_id)) ? Lang::message('settings.enabled') : Lang::message('settings.disabled')]) . "\r\n";
                    $text .= Lang::message("settings.title.lang", ["lang" => Lang::getCurrentLangDesc()]) . "\r\n";
                    $text .= Lang::message('settings.title.cooldown', ["cooldown" => $this->service->getCooldown($chat_id)]) . "\r\n";
                    $text .= Lang::message('settings.title.growth', ["type" => ($this->service->getGrowth($chat_id) == 1) ? Lang::message('settings.growth.ariphmetic') : Lang::message('settings.growth.geometric')]) . "\r\n";
                }

                break;
        }
        $inline_keyboard = $button_list;

        if ($message == null) {
            if ($showButtons) {
                Request::sendHtmlMessage($chat_id, $text, ["reply_markup" => ['inline_keyboard' => $inline_keyboard]]);
            } else {
                Request::sendHtmlMessage($chat_id, $text);
            }
        } else {
            Request::editMessageText($chat_id, $message['message_id'], $text, ["reply_markup" => ['inline_keyboard' => $inline_keyboard], "parse_mode" => "HTML"]);
        }
    }

    public function processCallback($callback)
    {
        $from = $callback['from'];
        $message = $callback['message'];
        $data = $callback['data'];
        $chat = $message['chat'];
        $chat_id = $chat['id'];
        $this->service->initLang($chat);
        $isAdminInChat = $this->service->isAdminInChat($from['id'], $chat);
        if (in_array($data, array_keys(Lang::availableLangs()))) {
            if ($isAdminInChat) {
                $qrez = $this->service->setLang($chat, $data);
                if ($qrez) {
                    Lang::init($data);
                }
                $this->sendSettings($chat, $message, null);
            } else {
                Request::answerCallbackQuery($callback['id'], Lang::message('settings.adminonly'));
            }
        } elseif (strpos($data, "set_") !== false) {
            if ($isAdminInChat) {
                switch ($data) {
                    case 'set_toggle_silent_mode':
                        $this->service->toggleSilentMode($chat_id);
                        break;

                    case 'set_0':
                        $this->service->setCooldown($chat_id, 0.1);
                        break;

                    case 'set_0.5':
                        $this->service->setCooldown($chat_id, 0.5);
                        break;

                    case 'set_1':
                        $this->service->setCooldown($chat_id, 1);
                        break;

                    case 'set_2':
                        $this->service->setCooldown($chat_id, 2);
                        break;

                    case 'set_10':
                        $this->service->setCooldown($chat_id, 10);
                        break;

                    case 'set_20':
                        $this->service->setCooldown($chat_id, 20);
                        break;

                    case 'set_another_growth':
                        $this->service->switchGrowth($chat_id);
                        break;

                    case 'set_another_access':
                        $this->service->switchAccess($chat_id);
                        break;

                    case 'set_back':
                        $data = null;
                        break;
                }
                $this->sendSettings($chat, $message, $data);
            } else {
                Request::answerCallbackQuery($callback['id'], Lang::message('settings.title'));
            }
        } elseif (strpos($data, "erase_") !== false) {
            $erase_chat_id = explode("_", $data)[1];
            $erase_chat = $this->service->getGroupName($erase_chat_id);
            if (strpos($data, "accept") !== false) {
                $this->service->deleteChat($erase_chat_id);
                $text = Lang::message('settings.erase.success', ['chat_id' => $erase_chat_id, 'chat' => $erase_chat]);
                Request::editMessageText($chat_id, $message['message_id'], $text, ["parse_mode" => "HTML"]);
            } elseif (strpos($data, "reject") !== false) {
                $text = Lang::message('settings.erase.cancel', ['chat_id' => $erase_chat_id, 'chat' => $erase_chat]);
                Request::editMessageText($chat_id, $message['message_id'], $text, ["parse_mode" => "HTML"]);
            } else {
                $text = Lang::message('settings.erase.confirm', ['chat_id' => $erase_chat_id, 'chat' => $erase_chat]);
                Request::editMessageText(
                    $chat_id,
                    $message['message_id'],
                    $text,
                    [
                        "parse_mode" => "HTML",
                        "reply_markup" => [
                            'inline_keyboard' => [
                                [
                                    [
                                        "text" => "✔️" . Lang::message("confirm.yes"),
                                        "callback_data" => $data . "_accept",
                                    ],
                                    [
                                        "text" => "❌" . Lang::message("confirm.no"),
                                        "callback_data" => $data . "_reject",
                                    ],
                                ],
                            ],
                        ],
                    ]
                );
            }
        }
    }
}
