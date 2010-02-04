/*
 * This javascript library uses jQuery to do its heavy lifting.  To replace jQuery with your js framework of choice,
 * simply update this file.
 */

/*
 * Form-related functions
 */

function addArrayField(parentElementID, elementString) {
	$('#' + parentElementID).append(elementString);
}

function removeArrayField(elementID) {
	$('#' + elementID).remove();
}

function loadSubform() {
	
}