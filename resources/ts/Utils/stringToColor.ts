export function stringToColor(str: string): string {
  // Hash function to generate a deterministic integer from the string
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }

  // Convert hash to hex color
  let color = '#';
  for (let i = 0; i < 3; i++) {
    let value = (hash >> (i * 8)) & 0xff;
    // Ensure the color is not too light (to be visible on white)
    value = Math.min(value, 200); // Adjust this threshold as needed
    color += ('00' + value.toString(16)).slice(-2);
  }

  return color;
}
