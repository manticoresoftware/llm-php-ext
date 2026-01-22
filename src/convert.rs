use ext_php_rs::convert::{FromZval, IntoZval};
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendHashTable, Zval};
use octolib::llm::Message as OctoMessage;
use serde_json::Value;

use crate::message::Message;

/// Convert PHP array or MessageCollection to Vec<octolib::Message>
pub fn php_to_messages(zval: &Zval) -> Result<Vec<OctoMessage>, PhpException> {
    // Try to get as MessageCollection first
    if let Some(collection) = <&crate::message::MessageCollection>::from_zval(zval) {
        return collection.to_octo();
    }

    // Fall back to array conversion
    if let Some(arr) = zval.array() {
        let mut messages = Vec::new();
        for (_, val) in arr.iter() {
            // Check if it's a Message object
            if let Some(msg) = <&Message>::from_zval(val) {
                messages.push(msg.to_octo()?);
                continue;
            }

            // Fall back to array conversion
            if let Some(msg_arr) = val.array() {
                let msg = Message::from_array(msg_arr)?;
                messages.push(msg.to_octo()?);
            }
        }
        Ok(messages)
    } else {
        Err(PhpException::from_class::<
            crate::error::LLMValidationException,
        >(
            "Messages must be an array or MessageCollection".to_string(),
        ))
    }
}

/// Convert JSON Value to PHP array recursively
pub fn json_value_to_php(value: &Value) -> PhpResult<Zval> {
    match value {
        Value::Null => Ok(Zval::new()),
        Value::Bool(b) => {
            let mut zval = Zval::new();
            zval.set_bool(*b);
            Ok(zval)
        }
        Value::Number(n) => {
            let mut zval = Zval::new();
            if let Some(i) = n.as_i64() {
                zval.set_long(i);
            } else if let Some(f) = n.as_f64() {
                zval.set_double(f);
            }
            Ok(zval)
        }
        Value::String(s) => {
            let mut zval = Zval::new();
            zval.set_string(s, false)?;
            Ok(zval)
        }
        Value::Array(arr) => {
            let mut php_arr = ZendHashTable::new();
            for (idx, val) in arr.iter().enumerate() {
                let php_val = json_value_to_php(val)?;
                php_arr.insert(idx as u64, php_val)?;
            }
            Ok(php_arr.into_zval(false)?)
        }
        Value::Object(obj) => {
            let mut php_arr = ZendHashTable::new();
            for (key, val) in obj.iter() {
                let php_val = json_value_to_php(val)?;
                php_arr.insert(key.as_str(), php_val)?;
            }
            Ok(php_arr.into_zval(false)?)
        }
    }
}
