<?php
/**
 * The standard Toast Widgetset for Sodapop
 *
 * @author michaelarace
 */
class Themes_Monochrome_Widgets {
	public static function applicationContainer($args = array(), $innerContent = '') {
		return '<html>'.$innerContent.'</html>';
	}

	public static function contentContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-content').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function datatableObject($args = array(), $innerXML = '') {

	}

	public static function errorboxContainer($args = array(), $innerContent = '') {
		return '<div class="error-box">'.$innerContent.'</div>';
	}

	public static function FOOTERContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-footer').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function headerContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-header').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function hiddenTag($args = array()) {
		return '<input type="hidden" id="'.$args['id'].'" name="'.$args['id'].'" value="'.htmlentities($args['value']).'" />';
	}

	public static function logoTag($args = array()) {
		if (isset($args['image'])) {
			return '<img alt="'.$args['title'].'" src="'.$args['image'].'" '.Themes_Monochrome_Widgets::standardArgs($args, 'logo', array('title', 'image')).' />';
		} else {
			return '<h1 '.Themes_Monochrome_Widgets::standardArgs($args, 'logo', array('title', 'image')).'>'.$args['title'].'</h1>';
		}
	}

	public static function metadataContainer($args = array(), $innerContent = '') {
		return '<head>'.$innerContent.'</head>';
	}

	public static function navtabsObject ($args = array(), $innerXML = '') {
		$tabs = simplexml_load_string($innerXML);
		$output = '<div class="navtabs-container"><ul '.Themes_Monochrome_Widgets::standardArgs($args, 'navtabs', array('current')).'>';
		foreach ($tabs->navtab as $tabKey => $tab) {
			$output .= '<li id="'.$tab->attributes()->id.'" class="navtab'.($args['current'] == $tab->attributes()->id ? ' current-navtab' : '').'"><a href="'.$tab->attributes()->url.'">'.$tab->attributes()->label.'</a></li>';
		}
		return $output.'</ul></div>';
	}

	public static function passwordTag ($args = array()) {
		$inline = false;
		if ($args['inline'] && $args['inline'] == 'true') {
			$inline = true;
		}
		return (!$inline ? '<div class="password-container">' : '').'<label for="'.$args['id'].'">'.$args['label'].'</label>'.(!$inline ? '<br />' : '').'<input type="password" name="'.$args['id'].'" '.(isset($args['value']) ? 'value="'.htmlentities($args['value']).'"' : '').' '.Themes_Monochrome_Widgets::standardArgs($args, 'input-password', array('value', 'label')).' />'.(!$inline ? '</div>' : '');
	}

	public static function screenContainer($args = array(), $innerContent = '') {
		return '<body '.Themes_Monochrome_Widgets::standardArgs($args, '').'>'.$innerContent.'</body>';
	}

	public static function stylesheetTag($args = array()) {
		return '<link rel="stylesheet" type="text/css" href="'.$args['file'].'" />';
	}

	public static function submitTag ($args = array()) {
		$inline = false;
		if ($args['inline'] && $args['inline'] == 'true') {
			$inline = true;
		}
		return (!$inline ? '<div class="submit-container">' : '').'<input type="submit" value="'.htmlentities($args['label']).'" '.Themes_Monochrome_Widgets::standardArgs($args, 'input-password', array('value', 'label')).' />'.(!$inline ? '</div>' : '');
	}

	public static function textTag ($args = array()) {
		$inline = false;
		if ($args['inline'] && $args['inline'] == 'true') {
			$inline = true;
		}
		return (!$inline ? '<div class="text-container">' : '').'<label for="'.$args['id'].'">'.$args['label'].'</label>'.(!$inline ? '<br />' : '').'<input type="text" name="'.$args['id'].'" '.(isset($args['value']) ? 'value="'.htmlentities($args['value']).'"' : '').' '.Themes_Monochrome_Widgets::standardArgs($args, 'input-text', array('value', 'label')).' />'.(!$inline ? '</div>' : '');
	}

	public static function topnavContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'topnav').'>'.$innerContent.'</div>';
	}


	/*
	 * Below are some helper functions
	 */
	private static function standardArgs($args, $defaultClass, $excludeArgs = array()) {
		$argString = '';
		foreach ($args as $key => $value) {
			if ($key != 'class' && !in_array($key, $excludeArgs)) {
				$argString .= $key .'="'.$value.'" ';
			}
		}
		return $argString.'class="'.($defaultClass ? $defaultClass : '').(isset($args['class']) ? ' '.$args['class'] : '').'"';
	}
}

