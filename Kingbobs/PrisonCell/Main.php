<?php

namespace Kingbobs;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class PrisonCellPlugin extends PluginBase
{
    private $prisonCells = [];
    private $defaultCellPrice = 100;
    private $messages;

    public function onEnable()
    {
        $this->loadConfig();
        $this->loadMessages();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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

    private function loadMessages()
    {
        $this->messages = (new Config($this->getDataFolder() . "messages.yml", Config::YAML, [
            "buy_success" => "You have bought cell '{cell}' for ${price}.",
            "buy_insufficient_funds" => "You don't have enough money to buy cell '{cell}'.",
            "remove_success" => "Cell '{cell}' removed successfully.",
            "cell_not_found" => "Cell '{cell}' does not exist.",
            "sign_header" => "§a[Buy Cell]",
            "sign_price_format" => "§7Price: ${price}",
            "sign_tap_to_buy" => "§7Tap to buy"
        ]))->getAll();
    }

    private function saveMessages()
    {
        $messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $messagesConfig->setAll($this->messages);
        $messagesConfig->save();
    }

    private function getMessage(string $key, array $params = []): string
    {
        $message = $this->messages[$key] ?? "";
        foreach ($params as $param => $value) {
            $message = str_replace("{" . $param . "}", $value, $message);
        }
        return $message;
    }

    public function onSignChange(SignChangeEvent $event)
    {
        $player = $event->getPlayer();
        if (!$player->hasPermission("cell.buy")) {
            return;
        }

        $lines = $event->getLines();
        if ($lines[0] !== $this->getMessage("sign_header")) {
            return;
        }

        $cell = $lines[1];
        if (isset($this->prisonCells[$cell])) {
            $event->setLine(0, $this->getMessage("sign_header"));
            $event->setLine(3, $this->getMessage("sign_tap_to_buy"));
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if ($block->getId() === Block::SIGN_POST || $block->getId() === Block::WALL_SIGN) {
            $tile = $player->getLevel()->getTile($block);
            if ($tile instanceof Sign) {
                $text = $tile->getText();

                if ($text[0] === $this->getMessage("sign_header")) {
                    $cell = $text[1];
                    $price = $this->defaultCellPrice;

                    if (isset($this->prisonCells[$cell])) {
                        if ($this->hasEnoughMoney($player, $price)) {
                            $this->deductMoney($player, $price);
                            $this->putInCell($player, $cell);
                            $player->sendMessage($this->getMessage("buy_success", ["cell" => $cell, "price" => $price]));
                        } else {
                            $player->sendMessage($this->getMessage("buy_insufficient_funds", ["cell" => $cell]));
                        }
                    } else {
                        $player->sendMessage($this->getMessage("cell_not_found", ["cell" => $cell]));
                    }
                }
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();

        if ($block->getId() === Block::SIGN_POST || $block->getId() === Block::WALL_SIGN) {
            $tile = $event->getPlayer()->getLevel()->getTile($block);
            if ($tile instanceof Sign) {
                $text = $tile->getText();
                if ($text[0] === $this->getMessage("sign_header")) {
                    $cell = $text[1];
                    if (isset($this->prisonCells[$cell])) {
                        $this->removeFromCell($cell);
                        unset($this->prisonCells[$cell]);
                        $event->getPlayer()->sendMessage($this->getMessage("remove_success", ["cell" => $cell]));
                    }
                }
            }
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        // Handle player join logic, if needed
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        // Handle player quit logic, if needed
    }

    private function hasEnoughMoney(Player $player, int $amount): bool
    {
        return EconomyAPI::getInstance()->myMoneyCheckFunction($player, $amount);
    }

    private function deductMoney(Player $player, int $amount)
    {
        EconomyAPI::getInstance()->reduceMoney($player, $amount);
    }

    private function putInCell(Player $player, string $cell)
    {
    $prisonCell = $this->prisonCells[$cell];
    $player->teleport($prisonCell);
    }

private function removeFromCell(string $cell)
{
    if (isset($this->prisonCells[$cell])) {
        $prisonCell = $this->prisonCells[$cell];
        
        // Remove the cell from your system
        // Example: Remove the block at the prison cell position
        $level = $prisonCell->getLevel();
        $level->setBlock($prisonCell, Block::get(Block::AIR));
        // Unset the prison cell from the array
        unset($this->prisonCells[$cell]);
    }
    
class CreateCellCommand extends PluginCommand
{
    /** @var PrisonCellPlugin */
    private $plugin;

    public function __construct(string $name, PrisonCellPlugin $plugin)
    {
        parent::__construct($name, $plugin);
        $this->plugin = $plugin;
        $this->setDescription("Create a prison cell");
        $this->setPermission("prison.cell.create");
        $this->setUsage("/createcell <cell>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be executed in-game.");
            return;
        }

        if (!$this->testPermission($sender)) {
            return;
        }

        if (count($args) !== 1) {
            $sender->sendMessage("Usage: " . $this->getUsage());
            return;
        }

        $cell = strtolower($args[0]);
        if (isset($this->plugin->prisonCells[$cell])) {
            $sender->sendMessage("The prison cell '$cell' already exists.");
            return;
        }

        $this->plugin->prisonCells[$cell] = $sender->getPosition();
        $sender->sendMessage("Prison cell '$cell' created successfully.");
    }
}

class RemoveCellCommand extends PluginCommand
{
    /** @var PrisonCellPlugin */
    private $plugin;

    public function __construct(string $name, PrisonCellPlugin $plugin)
    {
        parent::__construct($name, $plugin);
        $this->plugin = $plugin;
        $this->setDescription("Remove a prison cell");
        $this->setPermission("prison.cell.remove");
        $this->setUsage("/removecell <cell>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be executed in-game.");
            return;
        }

        if (!$this->testPermission($sender)) {
            return;
        }

        if (count($args) !== 1) {
            $sender->sendMessage("Usage: " . $this->getUsage());
            return;
        }

        $cell = strtolower($args[0]);
        if (!isset($this->plugin->prisonCells[$cell])) {
            $sender->sendMessage("The prison cell '$cell' does not exist.");
            return;
        }

        $this->plugin->removeFromCell($cell);
        $sender->sendMessage("Prison cell '$cell' removed successfully.");
    }

}
