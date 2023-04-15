<?php

namespace MuhammedHk18\AntiNuker;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

    public static array $warns = [];

    public array $blockBreaks = [];

    /**
     * @return void
     */

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->config = new Config($this->getDataFolder()."settings.yml", Config::YAML, [
            # Use the § symbol for color coding
            # Color code list https://minecraft.tools/en/color-code.php
            # warn types: ban or kick. disable 0
            #console logging disable 0
            "harder-breaking-blocks-ids" => [49],
            "middle-breaking-block-ids" => [17],
            "quick-breaking-blocks-ids" => [1 , 3, 12, 15, 21, 22, 48, 42, 56, 57, 133, 129, 16, 14, 41],
            "quick-breaking-block-count-per-5-second" => 40,
            "middle-breaking-block-count-per-5-second" => 30,
            "harder-breaking-block-count-per-5-second" => 20,
            "warn-count" => 5,
            "warn-type" => "ban",
            "allowed-worlds" => ["world"],
            "console-logging-warn" => "{%0} player hacking",
            "warn-message" => "plesage close hack.",
            "ban-reason" => "you banned from antihack",
            "kick-reason" => "you kicked from antihack",
        ]);
    }

    /**
     * @param $player
     * @return bool
     */

    public function isHaveWarn($player): bool{
        if($player instanceof Player) $player = $player->getName();
        return isset(self::$warns[$player]);
    }

    /**
     * @param $player
     * @return int
     */

    public function getPlayerWarn($player): int{
        if($player instanceof Player) $player = $player->getName();
        return self::$warns[$player] ?? 0;
    }

    /**
     * @param $player
     * @return void
     */

    public function addWarn($player): void{
        if($player instanceof Player) $player = $player->getName();

        isset(self::$warns[$player]) ? self::$warns[$player]++ : self::$warns[$player] = 1;
    }

    /**
     * @param $player
     * @return void
     */

    public function reduceWarn($player): void{
        if($player instanceof Player) $player = $player->getName();

        if($this->getPlayerWarn($player) <= 1){
            unset(self::$warns[$player]);
        }else{
            self::$warns[$player]--;
        }
    }

    /**
     * @param int $block
     * @return int
     */
    public function getMaxCountForNuke(int $block): int{

        $slowids = $this->config->get("harder-breaking-blocks-ids");
        $fastids = $this->config->get("quick-breaking-blocks-ids");
        $middleids = $this->config->get("middle-breaking-blocks-ids");

        if (in_array($block, (array)$fastids)) return (int)$this->config->get("quick-breaking-block-count-per-5-second");

        if (in_array($block, (array)$slowids)) return (int)$this->config->get("harder-breaking-block-count-per-5-second");

        if (in_array($block, (array)$middleids)) return (int)$this->config->get("middle-breaking-block-count-per-5-second");

        throw new PluginException("please make sure you have set settings.yml properly.")
    }

    /**
     * @param BlockBreakEvent $event
     * @return false|void
     */

    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        $worlds = $this->config->get("allowed-worlds");

        if((!in_array($player->getPosition()->getWorld()->getFolderName(), $worlds))) return false;

        $countMax = $this->getMaxCountForNuke($block->getId());
        if(!$countMax) return false;

        $hash = $block->getPosition()->getX() . ":" . $block->getPosition()->getY() . ":" . $block->getPosition()->getZ();
        $now = time();
        $breaks = &$this->blockBreaks[$player->getName()];
        $breaks[$hash] = $now;
        $count = 0;

        foreach ($breaks as $h => $time) {
            if ($h === $hash) continue;
            if (time() - $time < 5) {
                $count++;
                if ($count >= $countMax) {

                    if($this->config->get("warn-message")) $player->sendMessage($this->config->get("warn-message"));

                    if($this->getPlayerWarn($player) > $this->config->get("warn-count")){ // detected hack

                        if($this->config->get("warn-type")){
                            if($this->config->get("warn-type") === "kick"){
                                $player->kick($this->config->get("kick-reason"));
                                return;
                            }

                            if($this->config->get("warn-type") === "ban"){
                                $this->getServer()->getNameBans()->addBan($player->getName(), $this->config->get("ban-reason"));
                                $player->kick($this->config->get($this->config->get("ban-reason")));
                                return;
                            }
                        }
                    }

                    unset($breaks[$h]);
                    $this->addWarn($player);

                    if($this->config->get("console-logging-warn")) {
                        $msg = $this->config->get("console-log-message");
                        $msg = str_replace("{%0}", "", $player->getName());
                        $this->getServer()->getLogger()->info($msg);
                    }

                    $event->cancel(true);
                    return;
                }
            } else {
                unset($breaks[$h]);
            }
        }
    }
}