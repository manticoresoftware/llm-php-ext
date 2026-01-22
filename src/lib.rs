#![cfg_attr(windows, feature(abi_vectorcall))]

mod convert;
mod error;
mod llm_class;
mod message;
mod structured_builder;
mod tool_builder;

use ext_php_rs::prelude::*;

/// Module entry point
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
        .class::<llm_class::LLM>()
        .class::<llm_class::Response>()
        .class::<llm_class::Usage>()
        .class::<structured_builder::StructuredBuilder>()
        .class::<structured_builder::StructuredResponse>()
        .class::<tool_builder::ToolBuilder>()
        .class::<tool_builder::Tool>()
        .class::<tool_builder::ToolCall>()
        .class::<tool_builder::ToolResponse>()
        .class::<message::Message>()
        .class::<message::MessageCollection>()
        .class::<error::LLMException>()
        .class::<error::LLMValidationException>()
        .class::<error::LLMStructuredOutputException>()
}
