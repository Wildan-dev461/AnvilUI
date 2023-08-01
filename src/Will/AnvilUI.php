<?php

declare(strict_types=1);

namespace Will;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\Armor;
use pocketmine\item\Tool;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\player\Player;
use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

class AnvilUI extends PluginBase implements Listener {

    private Config $config;
    private EconomyAPI $economy;

    public function onEnable(): void {
        $this->getLogger()->info("AnvilUI enabled!");

        // Load the configuration file
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        // EconomyAPI
        $this->economy = EconomyAPI::getInstance();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(): void {
        $this->getLogger()->info("AnvilUI disabled!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "anvilui") {
            if ($sender instanceof Player) {
                $this->openAnvilForm($sender);
            } else {
                $sender->sendMessage("This command can only be executed in-game.");
            }
            return true;
        }

        return false;
    }

    private function openAnvilForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data !== null) {
                switch ($data) {
                    case 0:
                        $this->openRepairForm($player);
                        break;
                    case 1:
                        $this->openRenameForm($player);
                        break;
                }
            }
        });

        $form->setTitle(">> §6AnvilUI§r <<");
        $form->setContent("§eWelcome to AnvilUI\n§bSelect an option:");

        // Get the repair and rename costs from the config
        $repairCostMoney = (int) $this->config->get("repair-cost-money", 1000);
        $repairCostXP = (int) $this->config->get("repair-cost-xp", 10);
        $renameCost = (int) $this->config->get("rename-cost", 5000);

        // Add repair and rename buttons with their respective costs
        $form->addButton("Repair Item\nCost: $" . $repairCostMoney . " and " . $repairCostXP . " XP");
        $form->addButton("Rename Item\nCost: $" . $renameCost);

        $player->sendForm($form);
    }

    private function openRepairForm(Player $player): void {
        // Check if the player has enough money and XP to repair the item
        $repairCostMoney = (int) $this->config->get("repair-cost-money", 1000);
        $repairCostXP = (int) $this->config->get("repair-cost-xp", 10);
        $playerMoney = $this->economy->myMoney($player);
        $playerXP = $player->getXpManager()->getXpLevel();

        if ($playerMoney < $repairCostMoney || $playerXP < $repairCostXP) {
            $player->sendMessage("§l§c[ERROR] §r§cYou don't have enough money or XP to repair the item.");
            return;
        }

        // Get the item in hand
        $item = $player->getInventory()->getItem($player->getInventory()->getHeldItemIndex());

        // Check if the item is a tool or armor before attempting to repair it
        if ($item instanceof Tool || $item instanceof Armor) {
            // Deduct the repair cost from the player's money and XP
            $this->economy->reduceMoney($player, $repairCostMoney);
            $player->getXpManager()->subtractXpLevels($repairCostXP);

            // Repair the item in hand
            $item->setDamage(0);
            $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);

            $player->sendMessage("§l§7[§aSUCCESS§7] §r§eYour item has been repaired for §a$" . $repairCostMoney . " and " . $repairCostXP . " XP");
        } else {
            $player->sendMessage("§l§c[ERROR] §r§cYou cannot repair this item.");
        }
    }

    private function openRenameForm(Player $player): void {
        // Check if the player has enough money to rename the item
        $renameCost = (int) $this->config->get("rename-cost", 5000);
        $playerMoney = $this->economy->myMoney($player);
        if ($playerMoney < $renameCost) {
            $player->sendMessage("§l§c[ERROR] §r§cYou don't have enough money to rename the item.");
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data !== null && isset($data[1])) {
                $renameTo = (string) $data[1];
                $this->processRenameItem($player, $renameTo);
            }
        });

        $form->setTitle("Rename Item");
        $form->addLabel("Current Money: §a$" . $playerMoney);
        $form->addInput("Enter the new name for the item:");

        $player->sendForm($form);
    }

    private function processRenameItem(Player $player, string $newName): void {
        // Check if the player has enough money to rename the item
        $renameCost = (int) $this->config->get("rename-cost", 5000);
        $playerMoney = $this->economy->myMoney($player);
        if ($playerMoney < $renameCost) {
            $player->sendMessage("§l§c[ERROR] §r§cYou don't have enough money to rename the item.");
            return;
        }

        // Deduct the rename cost from the player's money
        $this->economy->reduceMoney($player, $renameCost);

        // Rename the item in hand
        $item = $player->getInventory()->getItem($player->getInventory()->getHeldItemIndex());
        $item->setCustomName($newName);
        $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);

        $player->sendMessage("§l§7[§aSUCCESS§7] §r§eYour item has been renamed to §a" . $newName . " §efor §a$" . $renameCost);
    }
}
