<?php
/**
 * The standard Toast Widgetset for Sodapop
 *
 * @author michaelarace
 */
class Themes_Monochrome_Widgets {
	public static function actionbuttonsContainer($args = array(), $innerContent = '') {
		if (isset($args['label'])) {
			$label = $args['label'];
			return '<fieldset '.Themes_Monochrome_Widgets::standardArgs($args, 'action-buttons', array('label')).'><legend>'.$label.'</legend>'.$innerContent.'<br style="clear: both;" /></fieldset>';
		} else {
			return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'action-buttons').'>'.$innerContent.'<br style="clear: both;" /></div>';
		}
	}

	public static function applicationContainer($args = array(), $innerContent = '') {
		return '<html>'.$innerContent.'</html>';
	}

	public static function contentContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-content').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function errorboxContainer($args = array(), $innerContent = '') {
		return '<div class="error-box">'.$innerContent.'</div>';
	}

	public static function fieldGroupContainer($args = array(), $innerContent = '') {
		return (!isset($args['border']) || $args['border'] == 'false' ? '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'field-group', array('border', 'label')).'>' : '<fieldset '.Themes_Monochrome_Widgets::standardArgs($args, 'field-group', array('border', 'label')).'><legend>'.html_entity_decode($args['label']).'</legend>').$innerContent.(!isset($args['border']) || $args['border'] == 'false' ? '</div>' : '</fieldset>');
	}
	
	public static function filterContainer($args = array(), $innerContent = '') {
		$button = 'Update';
		if (isset($args['button'])) {
			$button = $args['button'];
		}
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-filter', array('button', 'state')).'><form method="get">'.(isset($args['state']) ? html_entity_decode($args['state']) : '').$innerContent.'<br style="clear: both;" /><div class="screen-filter-button"><input type="submit" value="'.$button.'" /></div></form></div>';
	}

	public static function filteritemTag ($args = array()) {
		$id = $args['id'];
		$type = $args['type'];
		$default = $args['default'];
		$label = $args['label'];
		if ($type == 'select' && $args['options']) {
			$options = unserialize(html_entity_decode($args['options']));
		}
		$retval = '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-filter-item', array('id', 'type', 'default', 'label', 'options')).'><label for="filter_'.$id.'">'.$label.'</label><br />';
		if ($type == 'select' && isset($options) && is_array($options)) {
			$retval .= '<select id="filter_'.$id.'" name="filter_'.$id.'">';
			foreach ($options as $value => $option) {
				$retval .= '<option value="'.$value.'"'.($default == $value ? ' selected="true"' : '').'>'.$option.'</option>';
			}
			$retval .= '</select>';
		} else {
			$retval .= '<input type="text" id="filter_'.$id.'" name="filter_'.$id.'" value="'.htmlentities($default).'" />';
		}
		return $retval.'</div>';

	}

	public static function footerContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-footer').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function formbuttonTag($args = array()) {
		$url = $args['url'];
		$label = $args['label'];
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'action-buttons-form-button', array('label', 'url')).'><input type="button" value="'.htmlentities($label).'" onclick="this.form.action = \''.$url.'\'; this.form.submit();" /></div>';
	}

	public static function gridObject($args = array(), $innerXML = '') {
		$xml = simplexml_load_string($innerXML);
		$retval = '<table '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-grid').'>';
		if ($xml->gridhead) {
			$retval .= '<thead><tr>';
			foreach($xml->gridhead->children() as $gh) {
				$attributes = array();
				foreach ($gh->attributes() as $key => $value) {
					$attributes[$key] = $value;
				}
				$retval .= '<th '.Themes_Monochrome_Widgets::standardArgs($attributes, 'screen-grid-th'.(isset($attributes['order_by']) && $attributes['order_by'] == 'true' ? '-current' : '').(isset($attributes['order_direction']) ? '-'.strtolower($attributes['order_direction']) : '') , array('link', 'order_by', 'order_direction')).'>'.(isset($attributes['link']) ? '<a href="'.html_entity_decode($attributes['link']).'">' : '').$gh.(isset($attributes['link']) ? '</a>' : '').'</th>';
			}
			$retval .= '</tr></thead>';
		}
		if ($xml->pagination) {
			$attributes = array();
			foreach ($xml->pagination->attributes() as $key => $value) {
				$attributes[$key] = $value;
			}
			if ($attributes['type'] == 'simple') {
				$retval .= '<tfoot><tr><th colspan="'.count($xml->gridhead->children()).'"><div class="screen-grid-pagination"><div class="prev">'.($xml->pagination->startIndex > 0 ? '<a href="'.$attributes['prevlink'].'">&laquo; Previous</a>' : '&#160;').'</div>';
				$retval .= '<div class="displaying">'.($xml->pagination->total > 0 ? 'Displaying '.($xml->pagination->startindex + 1).' - '.($xml->pagination->startindex + $xml->pagination->numperpage < $xml->pagination->total ? $xml->pagination->startindex + $xml->pagination->numperpage : $xml->pagination->total - $xml->pagination->startindex).' of '.$xml->pagination->total : '&#160;').'</div>';
				$retval .= '<div class="next">'.($xml->pagination->startindex + $xml->pagination->numperpage < $xml->pagination->total  ? '<a href="'.$attributes['nextlink'].'">Next &raquo;</a>' : '&#160;').'</div></div></th></tr></tfoot>';
			}
		}
		$retval .= '<tbody>';
		$i = 0;
		foreach($xml->gridbody->children() as $gr) {
			$retval .= '<tr '.($i % 2 == 0 ? 'class="screen-grid-td-even"' : 'class="screen-grid-td-odd"').'>';
			foreach ($gr->children() as $gd) {
				if($gd->children() > 0) {
					$retval .= '<td>'.html_entity_decode($gd->asXML(), ENT_NOQUOTES).'</td>';
				} else {
					$retval .= '<td>'.$gd.'</td>';
				}
			}
			$retval .= '</tr>';
			$i++;
		}
		$retval .= '</tbody>';
		return $retval.'</table>';
	}

	public static function headerContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'screen-header').'>'.$innerContent.'<br style="clear: both;" /></div>';
	}

	public static function hiddenTag($args = array()) {
		return '<input type="hidden" id="'.$args['id'].'" name="'.$args['id'].'" value="'.htmlentities($args['value']).'" />';
	}

	public static function javascriptTag($args = array()) {
		return '<script src="'.$args['file'].'"></script>';
	}

	public static function listbuttonTag($args = array()) {
		$link = $args['link'];
		$label = $args['label'];
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, 'action-buttons-list-button', array('label', 'link')).'><form action="'.$link.'" method="get"><input type="submit" value="'.htmlentities($label).'" /></form></div>';
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

	public static function pageContainer($args = array(), $innerContent = '') {
		return '<div '.Themes_Monochrome_Widgets::standardArgs($args, '').'>'.$innerContent.'</div>';
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

	public static function stringinputTag($args = array()) {
		$id = isset($args['id']) ? $args['id'] : 'field_'.time();
		$array = isset($args['array']) && $args['array'] == 'true' ? true : false;
		$retval = '<div class="field-container-div">';
		if (isset($args['label'])) {
			$retval .= '<label for="'.$id.($array ? '[]' : '').'">'.$args['label'].'</label>';
		}
		if ($array) {
			$items = unserialize(html_entity_decode($args['default']));
			if (isset($args['readonly']) && $args['readonly'] == 'true' ) {
				if (is_array($items)) {
					foreach ($items as $item) {
						$retval .= '<span '.Themes_Monochrome_Widgets::standardArgs($args, 'field-string-readonly', array('id', 'label', 'default', 'array', 'readonly')).'>'.($item == '' ? '&#60;empty&#62;' : htmlspecialchars($item)).'</span><br />';
					}
				}
			} else {
				$retval .= '<div id="'.$id.'-container">';
				if (is_array($items)) {
					foreach ($items as $item) {
						$i = 1;
						if ($item != '') {
							$retval .= '<div id="'.$id.'_'.$i.'"><input type="text" name="'.$id.'[]" id="'.$id.'" maxlength="255" '.Themes_Monochrome_Widgets::standardArgs($args, 'field-string', array('id', 'label', 'default', 'array', 'readonly')).' value="'.htmlspecialchars($item).'" /><input type="button" value="Remove" onclick="removeArrayField(\''.$id.'_'.$i.'\');" /></div>';
							$i++;
						}
					}
				}
				$jsHtmlString = "&lt;div id=&quot;".$id."_' + rand + '&quot;&gt;&lt;input type=&quot;text&quot; name=&quot;".$id."[]&quot; id=&quot;".$id."&quot; maxlength=&quot;255&quot; ".htmlentities(Themes_Monochrome_Widgets::standardArgs($args, 'field-string', array('id', 'label', 'default', 'array', 'readonly')))." value=&quot;&quot; /&gt;&lt;input type=&quot;button&quot; value=&quot;Remove&quot; onclick=&quot;removeArrayField(\'".$id."_' + rand + '\');&quot; /&gt;&lt;/div&gt;";
				$retval .= '</div><div><input type="button" value="Add" onclick="rand = Math.ceil(Math.random() * 1000000); addArrayField(\''.$id.'-container\', \''.$jsHtmlString.'\');" /></div>';
				
			}
		} else {
			if (isset($args['readonly']) && $args['readonly'] == 'true' ) {
				$retval .= '<span '.Themes_Monochrome_Widgets::standardArgs($args, 'field-string-readonly', array('id', 'label', 'default', 'array', 'readonly')).'>'.(unserialize(html_entity_decode($args['default'])) == '' ? '&#60;empty&#62;' : htmlspecialchars(unserialize(html_entity_decode($args['default'])))).'</span>';
			} else {
				$retval .= '<input type="text" name="'.$id.'" id="'.$id.'" maxlength="255" '.Themes_Monochrome_Widgets::standardArgs($args, 'field-string', array('id', 'label', 'default', 'array', 'readonly')).' value="'.unserialize($args['default']).'" />';
			}
		}
		return $retval.'</div>';
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

	public static function successboxContainer($args = array(), $innerContent = '') {
		return '<div class="success-box">'.$innerContent.'</div>';
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

