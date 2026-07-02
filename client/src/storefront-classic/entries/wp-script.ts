import { exposeAjaxRoute } from "../../shared/utils/ajax";
import { bindIntegerInput } from "../../shared/utils/int-input";
import { exposeMoneyFormat } from "../../shared/utils/money";
import { exposePrintAsString } from "../../shared/utils/print";

exposeAjaxRoute();
exposeMoneyFormat();
exposePrintAsString();
bindIntegerInput(jQuery);
