/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * Interaction for the Blocks module
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
jsBackend.Blocks =
{
    // constructor
    init: function()
    {
	
        $('.js-tab-lang').each(function(index, el){

            var language = $(el).data('language');
             var $dropdown = $('#pages' + language);
             var $link = $('#link' + language);

             $dropdown.change(function(e){
     			$link.val($(this).val());
     		});

        });
    }
}

$(jsBackend.Blocks.init);
