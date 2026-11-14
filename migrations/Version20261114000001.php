<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : carte, recettes, stocks, commandes, achats, inventaires + contraintes CHECK';
    }

    public function up(Schema $schema): void
    {
        // Schéma généré par doctrine:migrations:diff puis complété à la main (voir CHECK en bas)
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer_order (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(10) NOT NULL, covers INT NOT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, table_id INT NOT NULL, server_id INT NOT NULL, INDEX IDX_3B1CE6A3ECFF285C (table_id), INDEX IDX_3B1CE6A31844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dining_table (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(30) NOT NULL, seats INT NOT NULL, UNIQUE INDEX UNIQ_2553802EA750E8 (label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dish (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(10) NOT NULL, price NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX UNIQ_957D8CB85E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dish_line (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(12, 3) NOT NULL, unit VARCHAR(5) NOT NULL, dish_id INT NOT NULL, ingredient_id INT DEFAULT NULL, preparation_id INT DEFAULT NULL, INDEX IDX_5B160BE7148EB0CB (dish_id), INDEX IDX_5B160BE7933FE08C (ingredient_id), INDEX IDX_5B160BE73DD9B8BA (preparation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ingredient (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, dimension VARCHAR(10) NOT NULL, stock_quantity NUMERIC(14, 3) NOT NULL, average_cost NUMERIC(14, 6) NOT NULL, min_stock NUMERIC(14, 3) NOT NULL, target_stock NUMERIC(14, 3) NOT NULL, barcode VARCHAR(32) DEFAULT NULL, allergens JSON NOT NULL, traces JSON NOT NULL, allergen_status VARCHAR(12) NOT NULL, allergen_source VARCHAR(10) DEFAULT NULL, off_product_name VARCHAR(255) DEFAULT NULL, off_fetched_at DATETIME DEFAULT NULL, off_error VARCHAR(255) DEFAULT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6BAF78705E237E06 (name), INDEX IDX_6BAF78702ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_B12D4A36B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_line (id INT AUTO_INCREMENT NOT NULL, theoretical_quantity NUMERIC(14, 3) NOT NULL, counted_quantity NUMERIC(14, 3) DEFAULT NULL, variance NUMERIC(14, 3) DEFAULT NULL, variance_value NUMERIC(12, 2) DEFAULT NULL, inventory_id INT NOT NULL, ingredient_id INT NOT NULL, INDEX IDX_9BB27BD89EEA759 (inventory_id), INDEX IDX_9BB27BD8933FE08C (ingredient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, price NUMERIC(10, 2) NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX UNIQ_7D053A935E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_course (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(60) NOT NULL, position INT NOT NULL, menu_id INT NOT NULL, INDEX IDX_C5D93761CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_course_dish (menu_course_id INT NOT NULL, dish_id INT NOT NULL, INDEX IDX_35234FF2405CAEA6 (menu_course_id), INDEX IDX_35234FF2148EB0CB (dish_id), PRIMARY KEY (menu_course_id, dish_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_line (id INT AUTO_INCREMENT NOT NULL, dish_name VARCHAR(120) NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, unit_cost NUMERIC(12, 4) NOT NULL, status VARCHAR(12) NOT NULL, note VARCHAR(255) DEFAULT NULL, wasted TINYINT NOT NULL, created_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, ready_at DATETIME DEFAULT NULL, served_at DATETIME DEFAULT NULL, order_id INT NOT NULL, dish_id INT NOT NULL, order_menu_id INT DEFAULT NULL, INDEX idx_order_line_status (status), INDEX IDX_9CE58EE18D9F6D38 (order_id), INDEX IDX_9CE58EE1148EB0CB (dish_id), INDEX IDX_9CE58EE13AAD4059 (order_menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_menu (id INT AUTO_INCREMENT NOT NULL, menu_name VARCHAR(120) NOT NULL, price NUMERIC(10, 2) NOT NULL, order_id INT NOT NULL, menu_id INT DEFAULT NULL, INDEX IDX_30F400848D9F6D38 (order_id), INDEX IDX_30F40084CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE preparation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, yield_quantity NUMERIC(12, 3) NOT NULL, yield_unit VARCHAR(5) NOT NULL, notes LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_F9F0AAF45E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE preparation_line (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(12, 3) NOT NULL, unit VARCHAR(5) NOT NULL, preparation_id INT NOT NULL, ingredient_id INT DEFAULT NULL, sub_preparation_id INT DEFAULT NULL, INDEX IDX_63E9E2353DD9B8BA (preparation_id), INDEX IDX_63E9E235933FE08C (ingredient_id), INDEX IDX_63E9E2355C7DEAAE (sub_preparation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase_line (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(12, 3) NOT NULL, unit VARCHAR(5) NOT NULL, unit_price NUMERIC(12, 4) NOT NULL, purchase_order_id INT NOT NULL, ingredient_id INT NOT NULL, INDEX IDX_A1A77C95A45D7E6A (purchase_order_id), INDEX IDX_A1A77C95933FE08C (ingredient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase_order (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, ordered_at DATETIME DEFAULT NULL, received_at DATETIME DEFAULT NULL, supplier_id INT NOT NULL, INDEX IDX_21E210B22ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(12) NOT NULL, quantity NUMERIC(14, 3) NOT NULL, unit_cost NUMERIC(14, 6) NOT NULL, loss_reason VARCHAR(15) DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, ingredient_id INT NOT NULL, created_by_id INT DEFAULT NULL, order_line_id INT DEFAULT NULL, purchase_order_id INT DEFAULT NULL, inventory_id INT DEFAULT NULL, INDEX idx_movement_created (created_at), INDEX IDX_BB1BC1B5933FE08C (ingredient_id), INDEX IDX_BB1BC1B5B03A8386 (created_by_id), INDEX IDX_BB1BC1B5BB01DC09 (order_line_id), INDEX IDX_BB1BC1B5A45D7E6A (purchase_order_id), INDEX IDX_BB1BC1B59EEA759 (inventory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, contact_name VARCHAR(120) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, UNIQUE INDEX UNIQ_9B2A6C7E5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A3ECFF285C FOREIGN KEY (table_id) REFERENCES dining_table (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A31844E6B7 FOREIGN KEY (server_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE dish_line ADD CONSTRAINT FK_5B160BE7148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dish_line ADD CONSTRAINT FK_5B160BE7933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE dish_line ADD CONSTRAINT FK_5B160BE73DD9B8BA FOREIGN KEY (preparation_id) REFERENCES preparation (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF78702ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory ADD CONSTRAINT FK_B12D4A36B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_line ADD CONSTRAINT FK_9BB27BD89EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_line ADD CONSTRAINT FK_9BB27BD8933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE menu_course ADD CONSTRAINT FK_C5D93761CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_course_dish ADD CONSTRAINT FK_35234FF2405CAEA6 FOREIGN KEY (menu_course_id) REFERENCES menu_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_course_dish ADD CONSTRAINT FK_35234FF2148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE18D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE1148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE13AAD4059 FOREIGN KEY (order_menu_id) REFERENCES order_menu (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_menu ADD CONSTRAINT FK_30F400848D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_menu ADD CONSTRAINT FK_30F40084CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE preparation_line ADD CONSTRAINT FK_63E9E2353DD9B8BA FOREIGN KEY (preparation_id) REFERENCES preparation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE preparation_line ADD CONSTRAINT FK_63E9E235933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE preparation_line ADD CONSTRAINT FK_63E9E2355C7DEAAE FOREIGN KEY (sub_preparation_id) REFERENCES preparation (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_line ADD CONSTRAINT FK_A1A77C95A45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_line ADD CONSTRAINT FK_A1A77C95933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B22ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5BB01DC09 FOREIGN KEY (order_line_id) REFERENCES order_line (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5A45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B59EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE SET NULL');

        // Garde-fous d'intégrité au niveau MySQL (en plus des contrôles applicatifs) :
        // le stock ne peut jamais devenir négatif, une ligne de recette désigne exactement un composant.
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT chk_ingredient_stock_non_negative CHECK (stock_quantity >= 0)');
        $this->addSql('ALTER TABLE dish_line ADD CONSTRAINT chk_dish_line_one_component CHECK ((ingredient_id IS NOT NULL) <> (preparation_id IS NOT NULL))');
        $this->addSql('ALTER TABLE dish_line ADD CONSTRAINT chk_dish_line_quantity_positive CHECK (quantity > 0)');
        $this->addSql('ALTER TABLE preparation_line ADD CONSTRAINT chk_prep_line_one_component CHECK ((ingredient_id IS NOT NULL) <> (sub_preparation_id IS NOT NULL))');
        $this->addSql('ALTER TABLE preparation_line ADD CONSTRAINT chk_prep_line_quantity_positive CHECK (quantity > 0)');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT chk_order_line_quantity_positive CHECK (quantity > 0)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3ECFF285C');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A31844E6B7');
        $this->addSql('ALTER TABLE dish_line DROP FOREIGN KEY FK_5B160BE7148EB0CB');
        $this->addSql('ALTER TABLE dish_line DROP FOREIGN KEY FK_5B160BE7933FE08C');
        $this->addSql('ALTER TABLE dish_line DROP FOREIGN KEY FK_5B160BE73DD9B8BA');
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF78702ADD6D8C');
        $this->addSql('ALTER TABLE inventory DROP FOREIGN KEY FK_B12D4A36B03A8386');
        $this->addSql('ALTER TABLE inventory_line DROP FOREIGN KEY FK_9BB27BD89EEA759');
        $this->addSql('ALTER TABLE inventory_line DROP FOREIGN KEY FK_9BB27BD8933FE08C');
        $this->addSql('ALTER TABLE menu_course DROP FOREIGN KEY FK_C5D93761CCD7E912');
        $this->addSql('ALTER TABLE menu_course_dish DROP FOREIGN KEY FK_35234FF2405CAEA6');
        $this->addSql('ALTER TABLE menu_course_dish DROP FOREIGN KEY FK_35234FF2148EB0CB');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE18D9F6D38');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE1148EB0CB');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE13AAD4059');
        $this->addSql('ALTER TABLE order_menu DROP FOREIGN KEY FK_30F400848D9F6D38');
        $this->addSql('ALTER TABLE order_menu DROP FOREIGN KEY FK_30F40084CCD7E912');
        $this->addSql('ALTER TABLE preparation_line DROP FOREIGN KEY FK_63E9E2353DD9B8BA');
        $this->addSql('ALTER TABLE preparation_line DROP FOREIGN KEY FK_63E9E235933FE08C');
        $this->addSql('ALTER TABLE preparation_line DROP FOREIGN KEY FK_63E9E2355C7DEAAE');
        $this->addSql('ALTER TABLE purchase_line DROP FOREIGN KEY FK_A1A77C95A45D7E6A');
        $this->addSql('ALTER TABLE purchase_line DROP FOREIGN KEY FK_A1A77C95933FE08C');
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY FK_21E210B22ADD6D8C');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5933FE08C');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5B03A8386');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5BB01DC09');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5A45D7E6A');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B59EEA759');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE dining_table');
        $this->addSql('DROP TABLE dish');
        $this->addSql('DROP TABLE dish_line');
        $this->addSql('DROP TABLE ingredient');
        $this->addSql('DROP TABLE inventory');
        $this->addSql('DROP TABLE inventory_line');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_course');
        $this->addSql('DROP TABLE menu_course_dish');
        $this->addSql('DROP TABLE order_line');
        $this->addSql('DROP TABLE order_menu');
        $this->addSql('DROP TABLE preparation');
        $this->addSql('DROP TABLE preparation_line');
        $this->addSql('DROP TABLE purchase_line');
        $this->addSql('DROP TABLE purchase_order');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('DROP TABLE supplier');
    }
}
