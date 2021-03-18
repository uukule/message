<?php

/**
 * 消息状态
 */
const MESSAGE_STATUS_WAIT = 0;//等待发送中
const MESSAGE_STATUS_SENTING = 1;//正在发送中
const MESSAGE_STATUS_FAIL = 2;//发送失败
const MESSAGE_STATUS_SUCCESS = 3;//发送已成功
const MESSAGE_STATUS_COMPLETE = 4;//发送已完成

const MESSAGE_TOUSER_NO_EXISTENT = 10010;//接收者不存在
const MESSAGE_TEMPLATE_NO_EXISTENT = 10011;//模板不存在