/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {
    
    function moveWeArePlanetManualTasks()
    {
        $("#weareplanet_notifications").find("li").each(function (key, element) {
            $("#header_infos #notification").closest("ul").append(element);
            var html = '<div class="component pull-md-right weareplanet-component"><ul>'+$(element).prop('outerHTML')+'</ul></div>';
            $('.notification-center').closest('.component').after(html);
        });
    }
    moveWeArePlanetManualTasks();
    
});