import {Box} from '@calvient/decal';
import {
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
  Legend,
} from 'recharts';
import {stringToColor} from '../../../../../Utils/stringToColor';

interface LineGraphFormatProps {
  data: Array<{name: string; value: number}>;
}

const LineFormat = ({data}: LineGraphFormatProps) => {
  const keys = Object.keys(data[0]).filter((key) => key !== 'name');

  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <LineChart width={400} height={400} data={data}>
          {keys.length > 1 && <Legend />}
          <CartesianGrid strokeDasharray='3 3' />
          <XAxis dataKey='name' />
          <YAxis />
          <Tooltip />
          {keys.map((key) => (
            <Line
              key={key}
              type='monotone'
              dataKey={key}
              stroke={stringToColor(key)}
              strokeWidth={3}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default LineFormat;
