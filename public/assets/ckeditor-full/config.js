/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	config.allowedContent = true;
	//config.protectedSource.push(/<i[^>]*><\/i>/g);
	//config.extraAllowedContent = 'span;ul;li;table;i;td;style;*[id];*(*);*{*}';
	
};
CKEDITOR.dtd.$removeEmpty['i'] = false	
