<?php


namespace wiligangster\CR;

use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\tile\Chest;
use pocketmine\utils\Config;

class Loader extends PluginBase
{

    /** @var Config */
    private $chestRefill;

    public function onEnable()
    {
        if (!file_exists($this->getDataFolder() . "/ChestRefill.yml")) {
            $this->saveResource("ChestRefill.yml");
        }
        $this->chestRefill = new Config($this->getDataFolder() . "/ChestRefill.yml", Config::YAML);
        $all = $this->chestRefill->getAll();
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use ($all) : void {
            /** @var Server $server */
            $server = $this->getServer();
            if ($all["all-chest-refill-worlds"] ?? false) {
                foreach ($server->getLevels() as $level) {
                    foreach ($level->getTiles() as $tile) {
                        if ($tile instanceof Chest) {
                            $chest = $tile->getInventory();
                            if ($chest->firstEmpty() !== -1) {
                                $randomLoot = $this->randomizeLoot();
                                foreach ($randomLoot as $item) {
                                    if (!is_null($slot = $this->slotFree($chest))) {
                                        $chest->setItem($slot, $item);
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($all["chest_coords"] ?? [] as $tile) {
                    $tile = explode(":", $tile);
                    $levelName = $tile[3] ?? "world";
                    if (!$server->isLevelLoaded($levelName)) $server->loadLevel($levelName);
                    $level = $server->getLevelByName($levelName);
                    if ($level instanceof Level) {
                        $tile = $level->getTile(new Vector3($tile[0] ?? 0, $tile[1] ?? 0, $tile[2] ?? 0));
                        if ($tile instanceof Chest) {
                            $chest = $tile->getInventory();
                            if ($chest->firstEmpty() !== -1) {
                                $randomLoot = $this->randomizeLoot();
                                foreach ($randomLoot as $item) {
                                    if (!is_null($slot = $this->slotFree($chest))) {
                                        $chest->setItem($slot, $item);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }), 20 * $this->chestRefill->get("chest_refill_time", 20));
    }


    /**
     * @param ChestInventory $chestInventory
     * @return int|null
     */
    public function slotFree(ChestInventory $chestInventory)
    {
        $slotFree = [];
        for ($slot = 0; $slot < $chestInventory->getDefaultSize(); $slot++) {
            if ($chestInventory->isSlotEmpty($slot)) {
                $slotFree[] = $slot;
            }
        }
        return !empty($slotFree) ? $slotFree[array_rand($slotFree)] : null;
    }

    /**
     * @return array
     */
    public function randomizeLoot()
    {
        $items = [];
        $allItems = $this->chestRefill->get("items", []);
        $itemsPerRefill = $this->chestRefill->get("items_per_refill", 0);
        $i = 0;
        while (++$i <= $itemsPerRefill) {
            foreach ($allItems as $item) {
                $item = explode(":", $item);
                $items[] = Item::get($item[0] ?? 0, $item[1] ?? 0, $item[2] ?? 0);
            }
        }
        shuffle($items);
        return $items;
    }
}
