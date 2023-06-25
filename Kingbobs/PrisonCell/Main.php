<?php

namespace Kingbobs;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use onebone\economyapi\EconomyAPI;

class PrisonCellPlugin extends PluginBase implements Listener
{
    /** @var Config */
    private $config;

    /** @var array */
    private $prisonCells = [];

    public function onEnable()
    {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->prisonCells = $this->config->get("prison_cells", []);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable()
    {
        $this->config->set("prison_cells", $this->prisonCells);
        $this->config->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "setcell") {
            if (!$sender instanceof Player) {
                $sender->sendMessage("This command can only be run in-game.");
                return true;
            }

            // Check if the player has permission to use the command
            if (!$sender->hasPermission("cell.set")) {
                $sender->sendMessage("You don't have permission to use this command.");
                return true;
            }

            // Check if the command has the correct number of arguments
            if (count($args) !== 1) {
                $sender->sendMessage("Usage: /setcell <cell>");
                return true;
            }

            $cell = $args[0];

            // Check if the cell already exists
            if (isset($this->prisonCells[$cell])) {
                $sender->sendMessage("Cell '$cell' already exists.");
                return true;
            }

            // Save the cell location to the config file
            $this->prisonCells[$cell] = $sender->getPosition()->asVector3();
            $this->config->set("prison_cells", $this->prisonCells);
            $this->config->save();

            $sender->sendMessage("Cell '$cell' set successfully.");
            return true;
        }

        if ($command->getName() === "removecell") {
            if (!$sender instanceof Player) {
                $sender->sendMessage("This command can only be run in-game.");
                return true;
            }

            // Check if the player has permission to use the command
            if (!$sender->hasPermission("cell.remove")) {
                $sender->sendMessage("You don't have permission to use this command.");
                return true;
            }

            // Check if the command has the correct number of arguments
            if (count($args) !== 1) {
                $sender->sendMessage("Usage: /removecell <cell>");
                return true;
            }

            $cell = $args[0];

            // Check if the cell exists
            if (!isset($this->prisonCells[$cell])) {
                $sender->sendMessage("Cell '$cell' does not exist.");
                return true;
            }

            // Remove the cell from the config
            unset($this->prisonCells[$cell]);
            $this->config->set("prison_cells", $this->prisonCells);
            $this->config->save();

            $sender->sendMessage("Cell '$cell' removed successfully.");
            return true;
        }

        return false;
    }

    public function onSignChange(SignChangeEvent $event)
    {
        $player = $event->getPlayer();
        if (!$player->hasPermission("cell.buy")) {
            return;
        }

        $lines = $event->getLines();
        if ($lines[0] !== "[Buy Cell]") {
            return;
        }

        $cell = $lines[1];
        if (isset($this->prisonCells[$cell])) {
            $event->setLine(0, "§a[Buy Cell]");
            $event->setLine(1, $cell);
            $event->setLine(2, "§7Price: $100");
            $event->setLine(3, "§7Tap to buy");
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if ($block->getId() === Block::SIGN_POST || $block->getId() === Block::WALL_SIGN) {
            $tile = $player->getLevel()->getTile($block);
            if ($tile instanceof Sign) {
                $lines = $tile->getText();

                if ($lines[0] === "§a[Buy Cell]") {
                    $cell = $lines[1];
                    $price = 100; // Change this to the desired cell price

                    if (isset($this->prisonCells[$cell])) {
                        // Check if the player has enough money
                        if ($this->hasEnoughMoney($player, $price)) {
                            // Deduct the cell price from the player's money
                            $this->deductMoney($player, $price);

                            // Teleport the player to the cell location
                            $cellLocation = $this->prisonCells[$cell];
                            $x = $cellLocation->x;
                            $y = $cellLocation->y;
                            $z = $cellLocation->z;
                            $level = $player->getLevel();
                            $position = new Position($x, $y, $z, $level);
                            $player->teleport($position);

                            $player->sendMessage("You have bought cell '$cell' for $$price.");
                        } else {
                            $player->sendMessage("You don't have enough money to buy cell '$cell'.");
                        }
                    }
                }
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();
        $tile = $event->getPlayer()->getLevel()->getTile($block);
        
        if ($tile instanceof Sign) {
            $lines = $tile->getText();

            if ($lines[0] === "§a[Buy Cell]") {
                $event->setCancelled();
            }
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $this->removeFromCell($player);
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $this->removeFromCell($player);
    }

    private function hasEnoughMoney(Player $player, int $amount): bool
    {
        return EconomyAPI::getInstance()->myMoneyCheckFunction($player, $amount);
    }

    private function deductMoney(Player $player, int $amount)
    {
        EconomyAPI::getInstance()->reduceMoney($player, $amount);
    }

    private function removeFromCell(Player $player)
    {
        // Remove the player from any cell they were in
    }
  private function loadConfig()
  {
    $this->saveDefaultConfig();
    $this->reloadConfig();
    $config = $this->getConfig();

    // Load prison cells from config
    $prisonCells = $config->get("prison_cells", []);
    foreach ($prisonCells as $cell => $cellData) {
        $x = $cellData["x"];
        $y = $cellData["y"];
        $z = $cellData["z"];
        $this->prisonCells[$cell] = new Position($x, $y, $z, $this->getServer()->getDefaultLevel());
    }

    // Load default cell price from config
    $this->defaultCellPrice = $config->get("cell_price", 100);
}

}
