# 2026 - Web Developer Interview Project

## Summary
The following can be written in your preferred languages, and optionally supported with your choice of code, diagrams, and any supplementary documentation. However, it should not rely on third-party libraries or frameworks (such as Laravel or Bootstrap). You are free to use a CSS preprocessor (like Sass) if you would like. We would like you to leverage the native capabilities of the Web Platform for this exercise.
Imagine an MVC mini-application of your choice: create a page hosting a form, and handle the validation and submission of that form to the application. The form should contain the following information:
1. Job title (required).
2. Job small script (optional, text area).
3. Job location country (options limited to Canada and USA, required).
4. Job location state/province (required).
5. Job reference file attachment (optional).
6. Job budget (required, radio buttons).

Consider validation and usability options and suggest how you would address these. The form is expected to adhere to accessibility and security standards as well as providing some responsive support for smaller screens. Valid submissions should be stored in a relational database (such as MySQL), and trigger a confirmation/summary email to "jobform@voices.com" (fictional email provided for example).
You are welcome to extend the requirements to showcase your thinking about the project.

## User Interface Outline
The form should conform roughly to the following layout on a desktop view. We do not expect a pixel-perfect or an exact replica, but the final result should have a similar layout and ideally similar look-and-feel to the figure below. Use your own discretion for other screen sizes. The usage of modern CSS features is recommended.
User Interface Requirements
1. Input fields should be highlighted in some way when they receive focus.
2. Input fields with errors should be highlighted in some way.
3. Error messages should appear below their related input fields.
4. The Job small script text area should have a current word count displayed near it.
5. The Job reference file attachment area is an optional challenge for bonus points.

## Database Requirements
1. Show a migration script to create the tables you need.

## Expected Delivery
We are hoping to see a functional demonstration of your mini-application, with the ability for us to take a look at the final deliverables. We understand that not all parts of the mini-application may be completed or fully functional within the time given. If that is the case, we encourage you to provide a short explanation on how you would have handled the parts you have not completed.
The goal of this exercise is to provide a platform to discuss your thought-process and your technical decisions. We will be expecting to understand the decisions made which lead to your final handling of all project aspects.