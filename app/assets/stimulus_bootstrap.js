import { startStimulusApp } from '@symfony/stimulus-bundle';
import FoodCatalogController from './controllers/food_catalog_controller.js';
import FoodFormController from './controllers/food_form_controller.js';
import SupplierDialogController from './controllers/supplier_dialog_controller.js';
import SearchableSelectController from './controllers/searchable_select_controller.js';
import SwipeActionsController from './controllers/swipe_actions_controller.js';
import StockListController from './controllers/stock_list_controller.js';
import ClickableRowController from './controllers/clickable_row_controller.js';
import PasswordVisibilityController from './controllers/password_visibility_controller.js';

const app = startStimulusApp();
app.register('food-catalog', FoodCatalogController);
app.register('food-form', FoodFormController);
app.register('supplier-dialog', SupplierDialogController);
app.register('searchable-select', SearchableSelectController);
app.register('swipe-actions', SwipeActionsController);
app.register('stock-list', StockListController);
app.register('clickable-row', ClickableRowController);
app.register('password-visibility', PasswordVisibilityController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
