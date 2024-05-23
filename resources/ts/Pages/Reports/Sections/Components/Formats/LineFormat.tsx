import {Box} from '@calvient/decal';
import {Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid} from 'recharts';

interface LineGraphFormatProps {
  data: Array<{name: string; value: number}>;
}

const LineFormat = ({data}: LineGraphFormatProps) => {
  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <LineChart width={400} height={400} data={data}>
          <CartesianGrid strokeDasharray='3 3' />
          <XAxis dataKey='name' />
          <YAxis />
          <Tooltip />
          <Line type='monotone' dataKey='value' stroke='#6C6897' strokeWidth={3} />
        </LineChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default LineFormat;
